<?php

namespace App\Services\GoogleAds;

use App\Models\Social\SocialAccount;
use App\Models\Social\SocialAd;
use App\Models\Social\SocialAdBreakdown;
use App\Models\Social\SocialAdDailyStat;
use App\Services\Sync\BaseSyncService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Pulls a Google Ads account's results for the fetch period:
 * - campaigns with their performance, engagement and conversions (social_ads),
 * - the account's results day by day (social_ad_daily_stats),
 * - each campaign's results by age, gender, country, device and network (social_ad_breakdowns).
 * Each section is independent: a refused query is a warning and the rest continues.
 */
class GoogleAdsSyncService extends BaseSyncService
{
    /** Steps with their rough share of the time. */
    protected const STEPS = ['profile' => 5, 'campaigns' => 25, 'daily' => 20, 'audience' => 50];

    /** Metrics every report supports. */
    protected const CORE_METRICS = ['metrics.cost_micros', 'metrics.impressions', 'metrics.clicks', 'metrics.conversions', 'metrics.conversions_value'];

    /** Engagement metrics: dropped (with a warning) if Google refuses them for a report. */
    protected const ENGAGEMENT_METRICS = ['metrics.interactions', 'metrics.engagements', 'metrics.video_trueview_views'];

    /** Metrics of the audience splits. */
    protected const BREAKDOWN_METRICS = 'metrics.cost_micros, metrics.impressions, metrics.clicks, metrics.conversions';

    protected GoogleAdsClient $google;

    protected SocialAccount $account;

    /** Country code per geo target constant ID, shared by every account of a run. */
    protected static array $countryCodes = [];

    protected function apiException(): string
    {
        return GoogleAdsException::class;
    }

    public function syncAccount(SocialAccount $account, ?CarbonInterface $since = null, ?callable $shouldStop = null, ?callable $onProgress = null): array
    {
        $this->begin(self::STEPS, $since, $shouldStop, $onProgress, (int) config('meta.history_days'));
        $this->account = $account;
        $counts = ['campaigns' => 0, 'days' => 0, 'ad breakdown rows' => 0];

        try {
            $this->google = GoogleAdsClient::forCompany($account->company);
            // Refresh the token once up front: with access revoked nothing else can work.
            $this->google->accessToken((string) $account->access_token);
        } catch (GoogleAdsException $e) {
            $account->update(['last_sync_error' => $e->getMessage()]);
            return ['counts' => [], 'warnings' => [$e->getMessage()]];
        }

        $this->progress->start('profile');
        $this->attempt('account details', fn () => $this->syncProfile());

        $this->progress->start('campaigns');
        $this->attempt('campaigns', function () use (&$counts) {
            $counts['campaigns'] = $this->syncCampaigns();
        });

        $this->progress->start('daily');
        $this->attempt('daily results', function () use (&$counts) {
            $counts['days'] = $this->syncDaily();
        });

        $this->progress->start('audience');
        $names = array_keys(config('google_ads.breakdowns'));
        foreach ($names as $i => $name) {
            $this->attempt("results by $name", function () use ($name, &$counts) {
                $counts['ad breakdown rows'] += $this->syncBreakdown($name);
            });
            $this->progress->count($i + 1, count($names));
        }

        return $this->finish($account, $counts);
    }

    // ------------------------------------------------------------- Sections

    protected function syncProfile(): void
    {
        $c = $this->rows('SELECT customer.descriptive_name, customer.currency_code, customer.time_zone FROM customer LIMIT 1')[0]['customer'] ?? [];

        $this->account->update([
            'name' => ($c['descriptiveName'] ?? '') ?: $this->account->name,
            'meta' => array_merge($this->account->meta ?? [], array_filter([
                'currency' => $c['currencyCode'] ?? null,
                'time_zone' => $c['timeZone'] ?? null,
            ])),
        ]);
    }

    /**
     * Every campaign that isn't removed, with its results over the period (zero
     * when it didn't run), plus removed ones that still spent in the period.
     * Campaigns stored by an earlier fetch that are in neither list are deleted,
     * so the table always describes the period of the last fetch.
     */
    protected function syncCampaigns(): int
    {
        $fields = 'campaign.id, campaign.name, campaign.status, campaign.serving_status, campaign.advertising_channel_type, campaign.start_date_time';

        $campaigns = [];
        foreach ($this->rows("SELECT $fields FROM campaign WHERE campaign.status != 'REMOVED'") as $row) {
            $campaigns[(string) $row['campaign']['id']] = ['attrs' => $row['campaign'], 'metrics' => []];
        }
        $this->checkStop();

        foreach ($this->withMetrics("SELECT $fields, {metrics} FROM campaign WHERE {range}", 'campaign results') as $row) {
            $campaigns[(string) $row['campaign']['id']] = ['attrs' => $row['campaign'], 'metrics' => $row['metrics'] ?? []];
        }

        $tz = $this->account->meta['time_zone'] ?? null;
        $done = 0;
        foreach ($campaigns as $id => ['attrs' => $a, 'metrics' => $m]) {
            $this->checkStop();
            SocialAd::updateOrCreate(
                ['social_account_id' => $this->account->id, 'external_id' => $id],
                [
                    'name' => $a['name'] ?? null,
                    'status' => $a['status'] ?? null,               // ENABLED | PAUSED | REMOVED
                    'effective_status' => $a['servingStatus'] ?? null, // SERVING | ENDED | PENDING | SUSPENDED | NONE
                    'campaign_name' => null,
                    'objective' => $a['advertisingChannelType'] ?? null, // SEARCH | PERFORMANCE_MAX | DISPLAY | VIDEO...
                    'started_at' => !empty($a['startDateTime'])
                        ? Carbon::parse($a['startDateTime'], $tz)->setTimezone(config('app.timezone'))
                        : null,
                ] + $this->metricValues($m)
            );
            $this->progress->count(++$done, count($campaigns));
        }

        SocialAd::where('social_account_id', $this->account->id)
            ->whereNotIn('external_id', array_map('strval', array_keys($campaigns)))
            ->delete();

        return count($campaigns);
    }

    /** The account's results per day. */
    protected function syncDaily(): int
    {
        $n = 0;
        foreach ($this->withMetrics('SELECT segments.date, {metrics} FROM customer WHERE {range}', 'daily results') as $row) {
            if (empty($row['segments']['date'])) {
                continue;
            }
            $values = $this->metricValues($row['metrics'] ?? []);
            unset($values['reach']);
            SocialAdDailyStat::updateOrCreate(
                ['social_account_id' => $this->account->id, 'date' => $row['segments']['date']],
                $values
            );
            $n++;
        }

        return $n;
    }

    /**
     * Each campaign's results split by one dimension, replacing the stored split.
     * Rows are read in full first, so a refused query keeps the previous data.
     */
    protected function syncBreakdown(string $name): int
    {
        $range = $this->range();
        $metrics = self::BREAKDOWN_METRICS;

        [$query, $segmentOf] = match ($name) {
            'age' => [
                "SELECT campaign.id, ad_group_criterion.age_range.type, $metrics FROM age_range_view WHERE $range",
                fn ($r) => $this->ageSegment($r['adGroupCriterion']['ageRange']['type'] ?? null),
            ],
            'gender' => [
                "SELECT campaign.id, ad_group_criterion.gender.type, $metrics FROM gender_view WHERE $range",
                fn ($r) => match ($r['adGroupCriterion']['gender']['type'] ?? null) {
                    'MALE' => 'male',
                    'FEMALE' => 'female',
                    default => 'unknown',
                },
            ],
            'country' => [
                "SELECT campaign.id, user_location_view.country_criterion_id, $metrics FROM user_location_view WHERE $range",
                fn ($r) => (string) ($r['userLocationView']['countryCriterionId'] ?? ''),
            ],
            'device' => [
                "SELECT campaign.id, segments.device, $metrics FROM campaign WHERE $range",
                fn ($r) => strtolower($r['segments']['device'] ?? 'unknown'),
            ],
            'network' => [
                "SELECT campaign.id, segments.ad_network_type, $metrics FROM campaign WHERE $range",
                fn ($r) => strtolower($r['segments']['adNetworkType'] ?? 'unknown'),
            ],
        };

        $rows = $this->rows($query);

        if ($name === 'country') {
            // Google answers with geo target IDs: turn them into country codes.
            $codes = $this->countryCodes(array_map(fn ($r) => (int) ($r['userLocationView']['countryCriterionId'] ?? 0), $rows));
            $segmentOf = fn ($r) => $codes[(int) ($r['userLocationView']['countryCriterionId'] ?? 0)] ?? 'unknown';
        }

        $now = now();
        $records = [];
        foreach ($rows as $r) {
            $campaignId = (string) ($r['campaign']['id'] ?? '');
            $segment = mb_substr((string) $segmentOf($r), 0, 150) ?: 'unknown';
            if ($campaignId === '') {
                continue;
            }
            $m = $this->metricValues($r['metrics'] ?? []);
            $key = "$campaignId|$segment";
            $records[$key] = [
                'social_account_id' => $this->account->id,
                'ad_external_id' => $campaignId,
                'breakdown' => $name,
                'segment' => $segment,
                'spend' => ($records[$key]['spend'] ?? 0) + $m['spend'],
                'impressions' => ($records[$key]['impressions'] ?? 0) + $m['impressions'],
                'reach' => 0,
                'clicks' => ($records[$key]['clicks'] ?? 0) + $m['clicks'],
                'conversions' => ($records[$key]['conversions'] ?? 0) + $m['conversions'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::transaction(function () use ($name, $records) {
            SocialAdBreakdown::where('social_account_id', $this->account->id)->where('breakdown', $name)->delete();
            // Upsert, not insert: segments MySQL treats as equal are one key. Their results are added up.
            foreach (array_chunk(array_values($records), 500) as $chunk) {
                SocialAdBreakdown::upsert(
                    $chunk,
                    ['social_account_id', 'ad_external_id', 'breakdown', 'segment'],
                    [
                        'spend' => DB::raw('`spend` + VALUES(`spend`)'),
                        'impressions' => DB::raw('`impressions` + VALUES(`impressions`)'),
                        'clicks' => DB::raw('`clicks` + VALUES(`clicks`)'),
                        'conversions' => DB::raw('`conversions` + VALUES(`conversions`)'),
                        'updated_at',
                    ]
                );
            }
        });

        return count($records);
    }

    // -------------------------------------------------------------- Helpers

    /** Run a query on this account, through its manager account when it has one. */
    protected function rows(string $query): array
    {
        $this->checkStop();

        return $this->google->searchAll(
            $this->account->external_id,
            $query,
            (string) $this->account->access_token,
            $this->account->meta['login_customer_id'] ?? null
        );
    }

    /**
     * Run a query with {metrics} and {range} filled in. If Google refuses the
     * engagement metrics for this report, run it again without them.
     */
    protected function withMetrics(string $template, string $what): array
    {
        $build = fn (array $metrics) => strtr($template, ['{metrics}' => implode(', ', $metrics), '{range}' => $this->range()]);

        try {
            return $this->rows($build(array_merge(self::CORE_METRICS, self::ENGAGEMENT_METRICS)));
        } catch (GoogleAdsException $e) {
            $rows = $this->rows($build(self::CORE_METRICS)); // throws again if the problem wasn't the metrics
            $this->warn("engagement metrics of the $what", $e);

            return $rows;
        }
    }

    protected function range(): string
    {
        return "segments.date BETWEEN '{$this->since->toDateString()}' AND '" . now()->toDateString() . "'";
    }

    /** Google's metrics (camelCase, zeros left out, int64 as strings) as column values. */
    protected function metricValues(array $m): array
    {
        return [
            'spend' => round(((int) ($m['costMicros'] ?? 0)) / 1_000_000, 2),
            'impressions' => (int) ($m['impressions'] ?? 0),
            'reach' => 0, // Google Ads has no reach per campaign over a period
            'clicks' => (int) ($m['clicks'] ?? 0),
            'conversions' => round((float) ($m['conversions'] ?? 0), 2),
            'conversion_value' => round((float) ($m['conversionsValue'] ?? 0), 2),
            'interactions' => (int) ($m['interactions'] ?? 0),
            'engagements' => (int) ($m['engagements'] ?? 0),
            'video_views' => (int) ($m['videoTrueviewViews'] ?? 0),
        ];
    }

    /** AGE_RANGE_25_34 → "25-34", AGE_RANGE_65_UP → "65+". */
    protected function ageSegment(?string $type): string
    {
        if ($type && preg_match('/^AGE_RANGE_(\d+)_(\d+|UP)$/', $type, $m)) {
            return $m[2] === 'UP' ? "{$m[1]}+" : "{$m[1]}-{$m[2]}";
        }

        return 'unknown';
    }

    /**
     * Country code of each geo target constant ID (2840 → "US").
     *
     * @param int[] $ids
     * @return array<int, string>
     */
    protected function countryCodes(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids)));
        $missing = array_values(array_diff($ids, array_keys(self::$countryCodes)));

        foreach (array_chunk($missing, 200) as $chunk) {
            $rows = $this->rows('SELECT geo_target_constant.id, geo_target_constant.country_code FROM geo_target_constant '
                . 'WHERE geo_target_constant.id IN (' . implode(',', $chunk) . ')');
            foreach ($rows as $r) {
                $geo = $r['geoTargetConstant'] ?? [];
                if (isset($geo['id'])) {
                    self::$countryCodes[(int) $geo['id']] = $geo['countryCode'] ?? 'unknown';
                }
            }
        }

        return array_intersect_key(self::$countryCodes, array_flip($ids));
    }
}
