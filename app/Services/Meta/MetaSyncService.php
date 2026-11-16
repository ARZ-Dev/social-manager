<?php

namespace App\Services\Meta;

use App\Models\Social\SocialAccount;
use App\Models\Social\SocialAd;
use App\Models\Social\SocialConversation;
use App\Models\Social\SocialInsight;
use App\Models\Social\SocialPost;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Pulls posts, comments, insights, messages and boosts for linked accounts and
 * stores them. Each section is independent: a failure (missing permission,
 * deprecated metric...) is recorded as a warning and the rest continues.
 */
class MetaSyncService
{
    /** @var string[] */
    protected array $warnings = [];

    /** @var string[] Metrics Meta rejected during this fetch. */
    protected array $badMetrics = [];

    protected CarbonInterface $since;

    public function __construct(protected MetaClient $meta)
    {
    }

    /**
     * @return array{counts: array<string,int>, warnings: string[]}
     */
    public function syncAccount(SocialAccount $account): array
    {
        $this->warnings = [];
        $this->badMetrics = [];
        $this->since = now()->subDays(config('meta.history_days'))->startOfDay();
        $counts = [];

        match ($account->platform) {
            SocialAccount::FACEBOOK => $counts = $this->syncFacebook($account),
            SocialAccount::INSTAGRAM => $counts = $this->syncInstagram($account),
            SocialAccount::ADS => $counts = $this->syncAds($account),
        };

        $account->update([
            'last_synced_at' => now(),
            'last_sync_error' => $this->warnings ? implode("\n", array_slice($this->warnings, 0, 10)) : null,
        ]);

        return ['counts' => $counts, 'warnings' => $this->warnings];
    }

    // ---------------------------------------------------------------- Facebook

    protected function syncFacebook(SocialAccount $account): array
    {
        $token = $account->access_token;
        $counts = ['posts' => 0, 'comments' => 0, 'insights' => 0, 'conversations' => 0];

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
        $counts = ['posts' => 0, 'comments' => 0, 'insights' => 0, 'conversations' => 0];

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
     * Metrics are requested one by one so a deprecated metric doesn't sink the rest.
     */
    protected function syncPostInsights(SocialAccount $account, SocialPost $post, string $metricSet, string $token): void
    {
        $values = $post->insights ?? [];

        foreach (config("meta.metrics.$metricSet") as $metric) {
            // Don't keep asking for a metric Meta already rejected during this fetch.
            if (in_array("$metricSet.$metric", $this->badMetrics)) {
                continue;
            }
            try {
                $res = $this->meta->get($post->external_id . '/insights', ['metric' => $metric], $token);
                foreach ($res['data'] ?? [] as $row) {
                    $value = $row['values'][0]['value'] ?? $row['total_value']['value'] ?? null;
                    // Breakdown metrics (e.g. reactions by type) come back as arrays: sum them.
                    $values[$row['name']] = is_array($value) ? array_sum($value) : $value;
                }
            } catch (MetaApiException $e) {
                $this->badMetrics[] = "$metricSet.$metric";
                $this->warnOnce($account, "post metric '$metric'", $e);
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

        foreach (config("meta.metrics.$metricSet") as $metric) {
            $from = $this->since->copy();
            try {
                while ($from->lt(now())) {
                    $to = $from->copy()->addDays($maxDays)->min(now());
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
        $counts = ['ads' => 0];

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

        return $counts;
    }

    // --------------------------------------------------------------- Helpers

    protected function attempt(SocialAccount $account, string $what, callable $fn): void
    {
        try {
            $fn();
        } catch (MetaApiException $e) {
            $this->warnings[] = ucfirst($what) . ': ' . $e->getMessage();
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
