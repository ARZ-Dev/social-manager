<?php

namespace App\Services\Meta;

use App\Models\Social\SocialAccount;
use App\Models\Social\SocialAd;
use App\Models\Social\SocialAdBreakdown;
use App\Models\Social\SocialConversation;
use App\Models\Social\SocialDemographic;
use App\Models\Social\SocialInsight;
use App\Models\Social\SocialPost;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Pulls posts, comments, insights, audience demographics, messages, boosts and
 * their breakdowns for linked accounts and stores them. Each section is independent: a failure (missing permission,
 * deprecated metric...) is recorded as a warning and the rest continues.
 */
class MetaSyncService
{
    /** @var string[] */
    protected array $warnings = [];

    /** @var string[] Metrics Meta rejected during this fetch. */
    protected array $badMetrics = [];

    protected CarbonInterface $since;

    protected MetaClient $meta;

    /** Returns true when the fetch should stop (Stop pressed or a newer fetch took over). */
    protected ?\Closure $shouldStop = null;

    /**
     * Fetch one account from $since (default: META_HISTORY_DAYS ago) until today.
     * $shouldStop is checked between steps; when it returns true a SyncStopped
     * exception ends the fetch, keeping what was saved so far.
     *
     * @return array{counts: array<string,int>, warnings: string[]}
     */
    public function syncAccount(SocialAccount $account, ?CarbonInterface $since = null, ?callable $shouldStop = null): array
    {
        $this->warnings = [];
        $this->badMetrics = [];
        $this->shouldStop = $shouldStop ? \Closure::fromCallable($shouldStop) : null;

        // Calls go through the owning company's own Meta app.
        try {
            $this->meta = MetaClient::forCompany($account->company);
        } catch (MetaApiException $e) {
            $account->update(['last_sync_error' => $e->getMessage()]);
            return ['counts' => [], 'warnings' => [$e->getMessage()]];
        }

        $this->since = $since
            ? Carbon::instance($since)->startOfDay()
            : now()->subDays(config('meta.history_days'))->startOfDay();
        $counts = [];

        match ($account->platform) {
            SocialAccount::FACEBOOK => $counts = $this->syncFacebook($account),
            SocialAccount::INSTAGRAM => $counts = $this->syncInstagram($account),
            SocialAccount::ADS => $counts = $this->syncAds($account),
        };

        // Remember which period the stored data covers: the Overview uses it to
        // know when a comparison with the previous period is meaningful.
        $meta = $account->fresh()->meta ?? [];
        $from = isset($meta['fetched_from']) ? Carbon::parse($meta['fetched_from'])->min($this->since) : $this->since;
        $account->update([
            'last_synced_at' => now(),
            'last_sync_error' => $this->warnings ? implode("\n", array_slice($this->warnings, 0, 10)) : null,
            'meta' => array_merge($meta, [
                'fetched_from' => $from->toDateString(),
                'last_fetch_since' => $this->since->toDateString(),
            ]),
        ]);

        return ['counts' => $counts, 'warnings' => $this->warnings];
    }

    // ---------------------------------------------------------------- Facebook

    protected function syncFacebook(SocialAccount $account): array
    {
        $token = $account->access_token;
        $counts = ['posts' => 0, 'comments' => 0, 'insights' => 0, 'audience segments' => 0, 'conversations' => 0];

        $this->attempt($account, 'page info', function () use ($account, $token) {
            $info = $this->meta->get($account->external_id, ['fields' => 'name,followers_count,fan_count,picture{url}'], $token);
            $account->update([
                'name' => $info['name'] ?? $account->name,
                'picture_url' => $info['picture']['data']['url'] ?? $account->picture_url,
                'meta' => array_merge($account->meta ?? [], [
                    'followers' => $info['followers_count'] ?? null,
                    'fans' => $info['fan_count'] ?? null,
                ]),
            ]);
        });

        $this->attempt($account, 'posts', function () use ($account, $token, &$counts) {
            $posts = $this->meta->paginate($account->external_id . '/posts', [
                'fields' => 'id,message,created_time,permalink_url,full_picture,status_type,shares,'
                    . 'reactions.summary(total_count).limit(0),comments.summary(total_count).limit(0)',
                'since' => $this->since->timestamp,
                'limit' => 100,
            ], $token);

            foreach ($posts as $item) {
                $this->checkStop();
                $post = SocialPost::updateOrCreate(
                    ['social_account_id' => $account->id, 'external_id' => $item['id']],
                    [
                        'media_type' => $item['status_type'] ?? null,
                        'message' => $item['message'] ?? null,
                        'media_url' => $item['full_picture'] ?? null,
                        'permalink' => $item['permalink_url'] ?? null,
                        'posted_at' => isset($item['created_time']) ? Carbon::parse($item['created_time']) : null,
                        'like_count' => $item['reactions']['summary']['total_count'] ?? 0,
                        'comment_count' => $item['comments']['summary']['total_count'] ?? 0,
                        'share_count' => $item['shares']['count'] ?? 0,
                    ]
                );
                $counts['posts']++;

                $counts['comments'] += $this->syncFacebookComments($account, $post, $token);
                $this->syncPostInsights($account, $post, 'facebook_post', $token);
            }
        });

        $this->attempt($account, 'page insights', function () use ($account, $token, &$counts) {
            $counts['insights'] = $this->syncAccountInsights($account, 'facebook_page', $token, 90);
        });

        $counts['audience segments'] = $this->syncPageDemographics($account, $token);

        $this->attempt($account, 'Messenger conversations', function () use ($account, $token, &$counts) {
            $counts['conversations'] = $this->syncConversations($account, $account->external_id, null, $token);
        });

        return $counts;
    }

    protected function syncFacebookComments(SocialAccount $account, SocialPost $post, string $token): int
    {
        if ($post->comment_count == 0) {
            return 0;
        }

        $n = 0;
        $this->attempt($account, "comments on post {$post->external_id}", function () use ($post, $token, &$n) {
            $comments = $this->meta->paginate($post->external_id . '/comments', [
                'fields' => 'id,message,created_time,from{name},like_count,parent{id}',
                'filter' => 'stream',
                'limit' => 100,
            ], $token);

            foreach ($comments as $c) {
                $post->comments()->updateOrCreate(
                    ['external_id' => $c['id']],
                    [
                        'parent_external_id' => $c['parent']['id'] ?? null,
                        'author_name' => $c['from']['name'] ?? null,
                        'message' => $c['message'] ?? null,
                        'like_count' => $c['like_count'] ?? 0,
                        'commented_at' => isset($c['created_time']) ? Carbon::parse($c['created_time']) : null,
                    ]
                );
                $n++;
            }
        });

        return $n;
    }

    // --------------------------------------------------------------- Instagram

    protected function syncInstagram(SocialAccount $account): array
    {
        $token = $account->access_token;
        $counts = ['posts' => 0, 'comments' => 0, 'insights' => 0, 'audience segments' => 0, 'conversations' => 0];

        $this->attempt($account, 'profile', function () use ($account, $token) {
            $info = $this->meta->get($account->external_id, [
                'fields' => 'username,name,profile_picture_url,followers_count,follows_count,media_count',
            ], $token);
            $account->update([
                'username' => $info['username'] ?? $account->username,
                'name' => $info['name'] ?? $account->name,
                'picture_url' => $info['profile_picture_url'] ?? $account->picture_url,
                'meta' => array_merge($account->meta ?? [], [
                    'followers' => $info['followers_count'] ?? null,
                    'following' => $info['follows_count'] ?? null,
                    'media_count' => $info['media_count'] ?? null,
                ]),
            ]);
        });

        $this->attempt($account, 'media', function () use ($account, $token, &$counts) {
            $media = $this->meta->paginate($account->external_id . '/media', [
                'fields' => 'id,caption,media_type,media_product_type,media_url,thumbnail_url,permalink,timestamp,like_count,comments_count',
                'since' => $this->since->timestamp,
                'limit' => 100,
            ], $token);

            foreach ($media as $item) {
                $this->checkStop();
                $post = SocialPost::updateOrCreate(
                    ['social_account_id' => $account->id, 'external_id' => $item['id']],
                    [
                        'media_type' => $item['media_product_type'] ?? $item['media_type'] ?? null,
                        'message' => $item['caption'] ?? null,
                        'media_url' => $item['media_url'] ?? null,
                        'thumbnail_url' => $item['thumbnail_url'] ?? null,
                        'permalink' => $item['permalink'] ?? null,
                        'posted_at' => isset($item['timestamp']) ? Carbon::parse($item['timestamp']) : null,
                        'like_count' => $item['like_count'] ?? 0,
                        'comment_count' => $item['comments_count'] ?? 0,
                    ]
                );
                $counts['posts']++;

                $counts['comments'] += $this->syncInstagramComments($account, $post, $token);
                $this->syncPostInsights($account, $post, 'instagram_media', $token);
            }
        });

        // Instagram allows at most 30 days per insights request.
        $this->attempt($account, 'account insights', function () use ($account, $token, &$counts) {
            $counts['insights'] = $this->syncAccountInsights($account, 'instagram_account', $token, 30);
        });

        $counts['audience segments'] = $this->syncInstagramDemographics($account, $token);

        // Instagram DMs are read through the linked Facebook Page.
        if ($account->parent) {
            $this->attempt($account, 'Instagram conversations', function () use ($account, $token, &$counts) {
                $counts['conversations'] = $this->syncConversations($account, $account->parent->external_id, 'instagram', $token);
            });
        }

        return $counts;
    }

    protected function syncInstagramComments(SocialAccount $account, SocialPost $post, string $token): int
    {
        if ($post->comment_count == 0) {
            return 0;
        }

        $n = 0;
        $this->attempt($account, "comments on media {$post->external_id}", function () use ($post, $token, &$n) {
            $comments = $this->meta->paginate($post->external_id . '/comments', [
                'fields' => 'id,text,timestamp,username,like_count,replies{id,text,timestamp,username,like_count}',
                'limit' => 100,
            ], $token);

            $store = function (array $c, ?string $parent) use ($post, &$n) {
                $post->comments()->updateOrCreate(
                    ['external_id' => $c['id']],
                    [
                        'parent_external_id' => $parent,
                        'author_name' => $c['username'] ?? null,
                        'message' => $c['text'] ?? null,
                        'like_count' => $c['like_count'] ?? 0,
                        'commented_at' => isset($c['timestamp']) ? Carbon::parse($c['timestamp']) : null,
                    ]
                );
                $n++;
            };

            foreach ($comments as $c) {
                $store($c, null);
                foreach ($c['replies']['data'] ?? [] as $reply) {
                    $store($reply, $c['id']);
                }
            }
        });

        return $n;
    }

    // ---------------------------------------------------------------- Shared

    /**
     * Lifetime metrics for one post, stored as metric => value on the post.
     * All metrics go in one request; if Meta rejects it (one retired or
     * unsupported metric fails the whole call), they are retried one by one so
     * the rest still come through, and the rejected one is skipped from then on.
     */
    protected function syncPostInsights(SocialAccount $account, SocialPost $post, string $metricSet, string $token): void
    {
        $values = $post->insights ?? [];
        $metrics = array_values(array_filter(
            config("meta.metrics.$metricSet"),
            fn ($m) => !in_array("$metricSet.$m", $this->badMetrics)
        ));
        if (!$metrics) {
            return;
        }

        $collect = function (array $res) use (&$values) {
            foreach ($res['data'] ?? [] as $row) {
                $value = $row['values'][0]['value'] ?? $row['total_value']['value'] ?? null;
                // Breakdown metrics (e.g. reactions by type) come back as arrays: sum them.
                $values[$row['name']] = is_array($value) ? array_sum($value) : $value;
            }
        };

        try {
            $collect($this->meta->get($post->external_id . '/insights', ['metric' => implode(',', $metrics)], $token));
        } catch (MetaApiException) {
            foreach ($metrics as $metric) {
                try {
                    $collect($this->meta->get($post->external_id . '/insights', ['metric' => $metric], $token));
                } catch (MetaApiException $e) {
                    $this->badMetrics[] = "$metricSet.$metric";
                    $this->warnOnce($account, "post metric '$metric'", $e);
                }
            }
        }

        $post->update(['insights' => $values]);
    }

    /**
     * Daily account metrics over the history window, chunked by $maxDays per request.
     */
    protected function syncAccountInsights(SocialAccount $account, string $metricSet, string $token, int $maxDays): int
    {
        $n = 0;
        $end = now(); // fixed once, so the loop always terminates

        foreach (config("meta.metrics.$metricSet") as $metric) {
            $from = $this->since->copy();
            try {
                while ($from->lt($end)) {
                    $this->checkStop();
                    $to = $from->copy()->addDays($maxDays)->min($end);
                    $res = $this->meta->get($account->external_id . '/insights', [
                        'metric' => $metric,
                        'period' => 'day',
                        'since' => $from->timestamp,
                        'until' => $to->timestamp,
                    ], $token);

                    foreach ($res['data'] ?? [] as $row) {
                        foreach ($row['values'] ?? [] as $v) {
                            if (!isset($v['end_time'])) {
                                continue;
                            }
                            $value = $v['value'] ?? 0;
                            SocialInsight::updateOrCreate(
                                [
                                    'social_account_id' => $account->id,
                                    'metric' => $row['name'],
                                    'date' => Carbon::parse($v['end_time'])->subDay()->toDateString(),
                                ],
                                ['value' => is_array($value) ? array_sum($value) : (int) $value]
                            );
                            $n++;
                        }
                    }
                    $from = $to;
                }
            } catch (MetaApiException $e) {
                $this->warnOnce($account, "account metric '$metric'", $e);
            }
        }

        return $n;
    }

    // ------------------------------------------------------------ Audience

    /**
     * Followers by country and city of a Facebook Page.
     */
    protected function syncPageDemographics(SocialAccount $account, string $token): int
    {
        $n = 0;

        foreach (config('meta.demographics.facebook_page') as $metric => $dimension) {
            $this->checkStop();
            try {
                $res = $this->meta->get($account->external_id . '/insights', ['metric' => $metric], $token);
                $n += $this->storeDemographics($account, $metric, $dimension, $this->segments($res, $dimension));
            } catch (MetaApiException $e) {
                $this->warnOnce($account, "audience metric '$metric'", $e);
            }
        }

        return $n;
    }

    /**
     * Followers and engaged audience of an Instagram account by age, gender,
     * country and city. One request per metric and breakdown.
     */
    protected function syncInstagramDemographics(SocialAccount $account, string $token): int
    {
        $cfg = config('meta.demographics.instagram_account');
        $n = 0;

        foreach ($cfg['metrics'] as $metric) {
            foreach ($cfg['breakdowns'] as $dimension) {
                $this->checkStop();
                try {
                    $res = $this->meta->get($account->external_id . '/insights', [
                        'metric' => $metric,
                        'period' => 'lifetime',
                        'metric_type' => 'total_value',
                        'timeframe' => $cfg['timeframe'],
                        'breakdown' => $dimension,
                    ], $token);
                    $n += $this->storeDemographics($account, $metric, $dimension, $this->segments($res, $dimension));
                } catch (MetaApiException $e) {
                    // Usually the account is under Meta's 100 followers / engagements threshold.
                    $this->warnOnce($account, "audience metric '$metric'", $e);
                }
            }
        }

        return $n;
    }

    /**
     * Turn a demographics response into [segment => count]. Handles both shapes:
     * Instagram's total_value.breakdowns and the Page metrics' map of
     * segment => count in values[].value (the latest one wins).
     *
     * @return array<string,int>
     */
    protected function segments(array $res, string $dimension): array
    {
        $out = [];

        foreach ($res['data'] ?? [] as $row) {
            foreach ($row['total_value']['breakdowns'] ?? [] as $breakdown) {
                $i = array_search($dimension, $breakdown['dimension_keys'] ?? [], true);
                foreach ($breakdown['results'] ?? [] as $result) {
                    $values = $result['dimension_values'] ?? [];
                    $segment = $i !== false ? ($values[$i] ?? null) : ($values ? $values[count($values) - 1] : null);
                    if ($segment !== null && $segment !== '') {
                        $out[$segment] = ($out[$segment] ?? 0) + (int) ($result['value'] ?? 0);
                    }
                }
            }

            $latest = collect($row['values'] ?? [])->last(fn ($v) => is_array($v['value'] ?? null) && $v['value']);
            foreach ($latest['value'] ?? [] as $segment => $value) {
                $out[(string) $segment] = (int) $value;
            }
        }

        return $out;
    }

    /**
     * Replace an account's snapshot for one metric and dimension.
     */
    protected function storeDemographics(SocialAccount $account, string $metric, string $dimension, array $segments): int
    {
        $now = now();
        $rows = [];
        foreach ($segments as $segment => $value) {
            $rows[] = [
                'social_account_id' => $account->id,
                'metric' => $metric,
                'dimension' => $dimension,
                'segment' => mb_substr((string) $segment, 0, 150),
                'value' => $value,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::transaction(function () use ($account, $metric, $dimension, $rows) {
            SocialDemographic::where('social_account_id', $account->id)
                ->where('metric', $metric)
                ->where('dimension', $dimension)
                ->delete();
            // Upsert, not insert: segments that differ only by accent, case or a
            // trailing space are one key to MySQL. Their counts are added up.
            foreach (array_chunk($rows, 500) as $chunk) {
                SocialDemographic::upsert(
                    $chunk,
                    ['social_account_id', 'metric', 'dimension', 'segment'],
                    ['value' => DB::raw('`value` + VALUES(`value`)'), 'updated_at']
                );
            }
        });

        return count($rows);
    }

    /**
     * Conversations of a Page (Messenger) or its Instagram inbox, with recent messages.
     */
    protected function syncConversations(SocialAccount $account, string $pageId, ?string $platform, string $token): int
    {
        $params = [
            'fields' => 'id,updated_time,participants,messages.limit(50){id,message,from,created_time}',
            'limit' => 50,
        ];
        if ($platform) {
            $params['platform'] = $platform;
        }

        $selfIds = array_filter([$pageId, $account->external_id]);
        $n = 0;

        foreach ($this->meta->paginate($pageId . '/conversations', $params, $token) as $conv) {
            $this->checkStop();
            $updated = isset($conv['updated_time']) ? Carbon::parse($conv['updated_time']) : null;
            if ($updated && $updated->lt($this->since)) {
                break; // newest first; the rest are older than the window
            }

            $other = collect($conv['participants']['data'] ?? [])
                ->first(fn ($p) => !in_array($p['id'] ?? null, $selfIds));
            $messages = $conv['messages']['data'] ?? [];

            $conversation = SocialConversation::updateOrCreate(
                ['social_account_id' => $account->id, 'external_id' => $conv['id']],
                [
                    'participant_name' => $other['name'] ?? $other['username'] ?? null,
                    'snippet' => $messages[0]['message'] ?? null,
                    'last_message_at' => $updated,
                ]
            );

            foreach ($messages as $m) {
                $conversation->messages()->updateOrCreate(
                    ['external_id' => $m['id']],
                    [
                        'from_name' => $m['from']['name'] ?? $m['from']['username'] ?? null,
                        'from_id' => $m['from']['id'] ?? null,
                        'from_page' => in_array($m['from']['id'] ?? null, $selfIds),
                        'message' => $m['message'] ?? null,
                        'sent_at' => isset($m['created_time']) ? Carbon::parse($m['created_time']) : null,
                    ]
                );
            }
            $n++;
        }

        return $n;
    }

    // ------------------------------------------------------------------- Ads

    protected function syncAds(SocialAccount $account): array
    {
        $counts = ['ads' => 0, 'ad breakdown rows' => 0];

        if ($account->token_expires_at && $account->token_expires_at->isPast()) {
            $this->warnings[] = 'Ad account access expired. Reconnect Facebook to renew it.';
            return $counts;
        }

        $this->attempt($account, 'boosts / ads', function () use ($account, &$counts) {
            $range = json_encode(['since' => $this->since->toDateString(), 'until' => now()->toDateString()]);
            $ads = $this->meta->paginate($account->external_id . '/ads', [
                'fields' => 'id,name,status,effective_status,created_time,campaign{name,objective},'
                    . 'creative{effective_object_story_id,effective_instagram_media_id},'
                    . "insights.time_range($range){spend,impressions,reach,clicks}",
                'filtering' => json_encode([[
                    'field' => 'updated_time', 'operator' => 'GREATER_THAN', 'value' => $this->since->timestamp,
                ]]),
                'limit' => 100,
            ], $account->access_token);

            foreach ($ads as $ad) {
                $this->checkStop();
                $stats = $ad['insights']['data'][0] ?? [];
                SocialAd::updateOrCreate(
                    ['social_account_id' => $account->id, 'external_id' => $ad['id']],
                    [
                        'name' => $ad['name'] ?? null,
                        'status' => $ad['status'] ?? null,
                        'effective_status' => $ad['effective_status'] ?? null,
                        'campaign_name' => $ad['campaign']['name'] ?? null,
                        'objective' => $ad['campaign']['objective'] ?? null,
                        'story_id' => $ad['creative']['effective_object_story_id'] ?? $ad['creative']['effective_instagram_media_id'] ?? null,
                        'spend' => $stats['spend'] ?? 0,
                        'impressions' => $stats['impressions'] ?? 0,
                        'reach' => $stats['reach'] ?? 0,
                        'clicks' => $stats['clicks'] ?? 0,
                        'started_at' => isset($ad['created_time']) ? Carbon::parse($ad['created_time']) : null,
                    ]
                );
                $counts['ads']++;
            }
        });

        foreach (config('meta.ad_breakdowns') as $name => $breakdowns) {
            $this->attempt($account, "ad breakdown '$name'", function () use ($account, $name, $breakdowns, &$counts) {
                $counts['ad breakdown rows'] += $this->syncAdBreakdown($account, $name, $breakdowns);
            });
        }

        return $counts;
    }

    /**
     * Per-ad results over the history window split by one breakdown set. Rows
     * are read in full before the old ones are replaced, so a failed request
     * keeps the previous data.
     */
    protected function syncAdBreakdown(SocialAccount $account, string $name, string $breakdowns): int
    {
        $dims = explode(',', $breakdowns);
        $rows = $this->meta->paginate($account->external_id . '/insights', [
            'level' => 'ad',
            'fields' => 'ad_id,spend,impressions,reach,clicks',
            'breakdowns' => $breakdowns,
            'time_range' => json_encode(['since' => $this->since->toDateString(), 'until' => now()->toDateString()]),
            'limit' => 500,
        ], $account->access_token);

        $now = now();
        $records = [];
        foreach ($rows as $row) {
            if (empty($row['ad_id'])) {
                continue;
            }
            $segment = mb_substr(collect($dims)->map(fn ($d) => $row[$d] ?? 'unknown')->join('|'), 0, 150);
            $key = $row['ad_id'] . '|' . $segment;

            $records[$key] = [
                'social_account_id' => $account->id,
                'ad_external_id' => $row['ad_id'],
                'breakdown' => $name,
                'segment' => $segment,
                'spend' => ($records[$key]['spend'] ?? 0) + (float) ($row['spend'] ?? 0),
                'impressions' => ($records[$key]['impressions'] ?? 0) + (int) ($row['impressions'] ?? 0),
                'reach' => ($records[$key]['reach'] ?? 0) + (int) ($row['reach'] ?? 0),
                'clicks' => ($records[$key]['clicks'] ?? 0) + (int) ($row['clicks'] ?? 0),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::transaction(function () use ($account, $name, $records) {
            SocialAdBreakdown::where('social_account_id', $account->id)->where('breakdown', $name)->delete();
            // Upsert, not insert: segments that differ only by accent, case or a
            // trailing space are one key to MySQL. Their results are added up.
            foreach (array_chunk(array_values($records), 500) as $chunk) {
                SocialAdBreakdown::upsert(
                    $chunk,
                    ['social_account_id', 'ad_external_id', 'breakdown', 'segment'],
                    [
                        'spend' => DB::raw('`spend` + VALUES(`spend`)'),
                        'impressions' => DB::raw('`impressions` + VALUES(`impressions`)'),
                        'reach' => DB::raw('`reach` + VALUES(`reach`)'),
                        'clicks' => DB::raw('`clicks` + VALUES(`clicks`)'),
                        'updated_at',
                    ]
                );
            }
        });

        return count($records);
    }

    // --------------------------------------------------------------- Helpers

    protected function attempt(SocialAccount $account, string $what, callable $fn): void
    {
        $this->checkStop();
        try {
            $fn();
        } catch (MetaApiException $e) {
            $this->warnings[] = ucfirst($what) . ': ' . $e->getMessage();
        }
    }

    /** @throws SyncStopped */
    protected function checkStop(): void
    {
        if ($this->shouldStop && ($this->shouldStop)()) {
            throw new SyncStopped('The fetch was stopped.');
        }
    }

    /** One warning per metric, not one per post. */
    protected function warnOnce(SocialAccount $account, string $what, MetaApiException $e): void
    {
        $line = ucfirst($what) . ': ' . $e->getMessage();
        if (!in_array($line, $this->warnings)) {
            $this->warnings[] = $line;
        }
    }
}
