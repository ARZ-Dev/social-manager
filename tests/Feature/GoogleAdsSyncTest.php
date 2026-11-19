<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Social\SocialAccount;
use App\Models\Social\SocialAd;
use App\Models\Social\SocialAdBreakdown;
use App\Models\Social\SocialAdDailyStat;
use App\Services\GoogleAds\GoogleAdsClient;
use App\Services\GoogleAds\GoogleAdsSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleAdsSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function company(): Company
    {
        return Company::create([
            'name' => 'Acme',
            'google_ads_developer_token' => 'dev-token-123',
            'google_ads_client_id' => '123-abc.apps.googleusercontent.com',
            'google_ads_client_secret' => 'GOCSPX-secret',
        ]);
    }

    /** Fake Google: answers each GAQL query by what it selects. */
    protected function fakeGoogle(): void
    {
        $results = fn (array $rows) => Http::response(['results' => $rows]);

        Http::fake(function (Request $request) use ($results) {
            if (str_contains($request->url(), 'oauth2.googleapis.com/token')) {
                return Http::response(['access_token' => 'access-1', 'expires_in' => 3600]);
            }
            if (str_contains($request->url(), 'customers:listAccessibleCustomers')) {
                return Http::response(['resourceNames' => ['customers/1112223333', 'customers/9998887777']]);
            }

            $q = $request['query'] ?? '';
            $customer = preg_match('#customers/(\d+)/googleAds#', $request->url(), $m) ? $m[1] : null;

            return match (true) {
                // discoverAccounts(): one direct account, one manager with a client
                str_contains($q, 'FROM customer LIMIT 1') && str_contains($q, 'customer.manager') => $results([[
                    'customer' => $customer === '9998887777'
                        ? ['id' => '9998887777', 'descriptiveName' => 'Agency MCC', 'manager' => true, 'status' => 'ENABLED']
                        : ['id' => '1112223333', 'descriptiveName' => 'Direct Co', 'currencyCode' => 'EUR', 'status' => 'ENABLED'],
                ]]),
                str_contains($q, 'FROM customer_client') => $results([
                    ['customerClient' => ['id' => '4445556666', 'descriptiveName' => 'Client Shop', 'currencyCode' => 'USD', 'timeZone' => 'Asia/Beirut', 'status' => 'ENABLED', 'level' => '1']],
                    // Also reachable directly: the direct listing must win.
                    ['customerClient' => ['id' => '1112223333', 'descriptiveName' => 'Direct Co', 'status' => 'ENABLED', 'level' => '1']],
                ]),

                // syncAccount()
                str_contains($q, 'FROM customer LIMIT 1') => $results([['customer' => ['descriptiveName' => 'Client Shop', 'currencyCode' => 'USD', 'timeZone' => 'Asia/Beirut']]]),
                str_contains($q, "campaign.status != 'REMOVED'") => $results([
                    ['campaign' => ['id' => '1', 'name' => 'Search - Brand', 'status' => 'ENABLED', 'servingStatus' => 'SERVING', 'advertisingChannelType' => 'SEARCH', 'startDateTime' => '2026-10-01 00:00:00']],
                    ['campaign' => ['id' => '2', 'name' => 'Display - Paused', 'status' => 'PAUSED', 'advertisingChannelType' => 'DISPLAY']],
                ]),
                str_contains($q, 'segments.device') => $results([
                    ['campaign' => ['id' => '1'], 'segments' => ['device' => 'MOBILE'], 'metrics' => ['costMicros' => '10000000', 'impressions' => '800', 'clicks' => '40', 'conversions' => 2]],
                    ['campaign' => ['id' => '1'], 'segments' => ['device' => 'DESKTOP'], 'metrics' => ['costMicros' => '2345678', 'impressions' => '200', 'clicks' => '10', 'conversions' => 0.5]],
                ]),
                str_contains($q, 'segments.ad_network_type') => $results([
                    ['campaign' => ['id' => '1'], 'segments' => ['adNetworkType' => 'SEARCH'], 'metrics' => ['costMicros' => '12345678', 'impressions' => '1000', 'clicks' => '50']],
                ]),
                str_contains($q, 'FROM campaign WHERE segments.date') => $results([
                    ['campaign' => ['id' => '1', 'name' => 'Search - Brand', 'status' => 'ENABLED', 'servingStatus' => 'SERVING', 'advertisingChannelType' => 'SEARCH'],
                        'metrics' => ['costMicros' => '12345678', 'impressions' => '1000', 'clicks' => '50', 'conversions' => 2.5, 'conversionsValue' => 300.75, 'interactions' => '55', 'videoTrueviewViews' => '7']],
                    // Removed, but it still spent in the period.
                    ['campaign' => ['id' => '3', 'name' => 'Old promo', 'status' => 'REMOVED', 'advertisingChannelType' => 'PERFORMANCE_MAX'],
                        'metrics' => ['costMicros' => '1000000', 'impressions' => '10']],
                ]),
                // Daily report refuses the engagement metrics: the sync retries without them.
                str_contains($q, 'FROM customer WHERE segments.date') && str_contains($q, 'metrics.engagements') => Http::response(['error' => [
                    'code' => 400, 'status' => 'INVALID_ARGUMENT', 'message' => 'Request contains an invalid argument.',
                    'details' => [['errors' => [['errorCode' => ['queryError' => 'PROHIBITED_METRIC_IN_SELECT_OR_WHERE_CLAUSE'], 'message' => 'Cannot select engagements here.']]]],
                ]], 400),
                str_contains($q, 'FROM customer WHERE segments.date') => $results([
                    ['segments' => ['date' => now()->subDay()->toDateString()], 'metrics' => ['costMicros' => '5000000', 'impressions' => '400', 'clicks' => '20', 'conversions' => 1]],
                    ['segments' => ['date' => now()->toDateString()], 'metrics' => ['costMicros' => '8345678', 'impressions' => '610', 'clicks' => '30', 'conversions' => 1.5]],
                ]),
                str_contains($q, 'FROM age_range_view') => $results([
                    ['campaign' => ['id' => '1'], 'adGroupCriterion' => ['ageRange' => ['type' => 'AGE_RANGE_25_34']], 'metrics' => ['costMicros' => '6000000', 'impressions' => '500', 'clicks' => '25', 'conversions' => 1]],
                    ['campaign' => ['id' => '1'], 'adGroupCriterion' => ['ageRange' => ['type' => 'AGE_RANGE_65_UP']], 'metrics' => ['impressions' => '20']],
                ]),
                str_contains($q, 'FROM gender_view') => $results([
                    ['campaign' => ['id' => '1'], 'adGroupCriterion' => ['gender' => ['type' => 'FEMALE']], 'metrics' => ['costMicros' => '7000000', 'impressions' => '600']],
                ]),
                str_contains($q, 'FROM user_location_view') => $results([
                    ['campaign' => ['id' => '1'], 'userLocationView' => ['countryCriterionId' => '2422'], 'metrics' => ['costMicros' => '9000000', 'impressions' => '900']],
                    ['campaign' => ['id' => '1'], 'userLocationView' => ['countryCriterionId' => '2840'], 'metrics' => ['costMicros' => '1000000', 'impressions' => '100']],
                ]),
                str_contains($q, 'FROM geo_target_constant') => $results([
                    ['geoTargetConstant' => ['id' => '2422', 'countryCode' => 'LB']],
                    ['geoTargetConstant' => ['id' => '2840', 'countryCode' => 'US']],
                ]),
                default => Http::response(['error' => ['code' => 400, 'message' => "Unexpected query: $q"]], 400),
            };
        });
    }

    public function test_discover_accounts_lists_direct_and_manager_clients(): void
    {
        $this->fakeGoogle();

        $found = GoogleAdsClient::forCompany($this->company())->discoverAccounts('refresh-1');
        $byId = collect($found['accounts'])->keyBy('id');

        $this->assertCount(2, $byId);
        $this->assertNull($byId['1112223333']['login_customer_id']); // direct access wins
        $this->assertSame('9998887777', $byId['4445556666']['login_customer_id']);
        $this->assertSame('Agency MCC', $byId['4445556666']['manager_name']);
        $this->assertSame([], $found['skipped']);
    }

    public function test_sync_stores_campaigns_daily_results_and_audience(): void
    {
        $this->fakeGoogle();
        $account = SocialAccount::create([
            'company_id' => $this->company()->id,
            'platform' => SocialAccount::GOOGLE_ADS,
            'external_id' => '4445556666',
            'name' => 'Old name',
            'access_token' => 'refresh-1',
            'meta' => ['login_customer_id' => '9998887777'],
        ]);
        // A campaign from an earlier fetch that no longer exists.
        SocialAd::create(['social_account_id' => $account->id, 'external_id' => '999', 'name' => 'Gone']);

        $result = app(GoogleAdsSyncService::class)->syncAccount($account->load('company'), now()->subDays(30));
        $account->refresh();

        // Profile
        $this->assertSame('Client Shop', $account->name);
        $this->assertSame('USD', $account->meta['currency']);
        $this->assertNotNull($account->last_synced_at);

        // Campaigns: enabled + paused + removed-with-spend; the stale one is gone.
        $ads = SocialAd::where('social_account_id', $account->id)->get()->keyBy('external_id');
        $this->assertEqualsCanonicalizing(['1', '2', '3'], $ads->keys()->all());
        $this->assertSame('12.35', $ads['1']->spend);
        $this->assertSame(1000, $ads['1']->impressions);
        $this->assertSame('2.50', $ads['1']->conversions);
        $this->assertSame('300.75', $ads['1']->conversion_value);
        $this->assertSame(7, $ads['1']->video_views);
        $this->assertSame('SEARCH', $ads['1']->objective);
        $this->assertSame('0.00', $ads['2']->spend);
        $this->assertSame('REMOVED', $ads['3']->status);
        $this->assertSame(3, $result['counts']['campaigns']);

        // Daily results, read again without the engagement metrics, with a warning.
        $this->assertSame(2, SocialAdDailyStat::where('social_account_id', $account->id)->count());
        $this->assertSame('8.35', SocialAdDailyStat::where('social_account_id', $account->id)->whereDate('date', now())->value('spend'));
        $this->assertStringContainsString('Cannot select engagements here.', $account->last_sync_error);

        // Audience splits
        $segments = fn (string $b) => SocialAdBreakdown::where('social_account_id', $account->id)->where('breakdown', $b)->pluck('segment')->sort()->values()->all();
        $this->assertSame(['25-34', '65+'], $segments('age'));
        $this->assertSame(['female'], $segments('gender'));
        $this->assertSame(['LB', 'US'], $segments('country'));
        $this->assertSame(['desktop', 'mobile'], $segments('device'));
        $this->assertSame(['search'], $segments('network'));
        $this->assertEquals(2.5, (float) SocialAdBreakdown::where('social_account_id', $account->id)->where('breakdown', 'device')->sum('conversions'));

        // Every report went through the manager account with the developer token.
        Http::assertSent(fn ($r) => str_contains($r->url(), 'googleAds:search')
            && $r->hasHeader('developer-token', 'dev-token-123')
            && $r->hasHeader('login-customer-id', '9998887777')
            && $r->hasHeader('Authorization', 'Bearer access-1'));
    }

    public function test_revoked_access_is_reported_without_fetching(): void
    {
        Http::fake(['oauth2.googleapis.com/*' => Http::response(['error' => 'invalid_grant', 'error_description' => 'Token has been expired or revoked.'], 400)]);
        $account = SocialAccount::create([
            'company_id' => $this->company()->id, 'platform' => SocialAccount::GOOGLE_ADS,
            'external_id' => '4445556666', 'access_token' => 'refresh-1',
        ]);

        app(GoogleAdsSyncService::class)->syncAccount($account->load('company'));

        $this->assertStringContainsString('Connect Google Ads again', $account->fresh()->last_sync_error);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'googleads.googleapis.com'));
    }
}
