<?php

namespace Tests\Feature;

use App\Jobs\SyncSocialAccounts;
use App\Livewire\Social\SocialMediaView;
use App\Models\Company;
use App\Models\Social\SocialAccount;
use App\Models\Social\SocialAd;
use App\Models\Social\SocialAdBreakdown;
use App\Models\Social\SocialAdDailyStat;
use App\Models\Social\SocialBulkMessage;
use App\Models\Social\SocialConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SocialMediaViewTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'Acme']);
        $this->user = User::create([
            'username' => 'tester', 'first_name' => 'Test', 'last_name' => 'User', 'phone' => '0',
            'email' => 'tester@example.test', 'password' => 'password', 'company_id' => $this->company->id,
        ]);
        foreach (['social-list', 'social-connect', 'social-sync', 'social-disconnect'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        $this->user->givePermissionTo(['social-list', 'social-connect', 'social-sync', 'social-disconnect']);
    }

    protected function seedAccounts(): array
    {
        $page = SocialAccount::create(['company_id' => $this->company->id, 'platform' => 'facebook', 'external_id' => 'p1', 'name' => 'Acme Page', 'last_synced_at' => now()]);
        $conv = SocialConversation::create(['social_account_id' => $page->id, 'external_id' => 'c1', 'participant_name' => 'Rita', 'last_message_at' => now()->subHour()]);
        $conv->messages()->create(['external_id' => 'm1', 'from_page' => false, 'message' => 'Hi', 'sent_at' => now()->subHours(3)]);
        $conv->messages()->create(['external_id' => 'm2', 'from_page' => true, 'message' => 'Hello!', 'sent_at' => now()->subHours(3)->addMinutes(12)]);
        $conv->messages()->create(['external_id' => 'm3', 'from_page' => false, 'message' => 'Price?', 'sent_at' => now()->subHour()]);

        $google = SocialAccount::create([
            'company_id' => $this->company->id, 'platform' => 'google_ads', 'external_id' => '4445556666', 'name' => 'Client Shop',
            'meta' => ['currency' => 'USD', 'last_fetch_since' => now()->subDays(30)->toDateString()], 'last_synced_at' => now(),
        ]);
        SocialAd::create(['social_account_id' => $google->id, 'external_id' => '1', 'name' => 'Search - Brand', 'status' => 'ENABLED',
            'effective_status' => 'SERVING', 'objective' => 'PERFORMANCE_MAX', 'spend' => 120.5, 'impressions' => 5000, 'clicks' => 250, 'conversions' => 12.5, 'conversion_value' => 900]);
        SocialAdDailyStat::create(['social_account_id' => $google->id, 'date' => now()->subDay()->toDateString(), 'spend' => 20, 'impressions' => 900, 'clicks' => 40, 'conversions' => 2]);
        SocialAdBreakdown::create(['social_account_id' => $google->id, 'ad_external_id' => '1', 'breakdown' => 'age', 'segment' => '25-34', 'spend' => 60, 'impressions' => 2500, 'clicks' => 120, 'conversions' => 6]);

        $bsb = SocialAccount::create([
            'company_id' => $this->company->id, 'platform' => 'bsb', 'external_id' => SocialAccount::bsbFingerprint('k'), 'name' => 'Main BSB',
            'access_token' => json_encode(['api_key' => 'k', 'api_secret' => 's']), 'meta' => ['sms_balance' => 1250.5, 'wp_balance' => 30], 'last_synced_at' => now(),
        ]);
        foreach ([['1', 'DELIVERED', '96170123456', 'LB', 'sms'], ['2', 'FAILED', '971501234567', 'AE', 'sms'], ['3', 'PENDING', '96171222333', 'LB', 'wp']] as [$ack, $status, $to, $cc, $route]) {
            SocialBulkMessage::create(['social_account_id' => $bsb->id, 'route' => $route, 'ack' => $ack, 'status' => $status, 'recipient' => $to,
                'country' => $cc, 'first_seen_at' => now()->subHours(2), 'status_changed_at' => now()->subHour()]);
        }

        return compact('page', 'google', 'bsb');
    }

    public function test_every_tab_renders_with_google_ads_and_bsb(): void
    {
        $this->seedAccounts();

        $page = Livewire::actingAs($this->user)->test(SocialMediaView::class)
            ->assertSee('Client Shop')
            ->assertSee('SMS 1.3K');

        $page->call('setTab', 'messages')
            ->assertSee('Median first response')
            ->assertSee('12m')                // Rita's first reply
            ->assertSee('1 awaiting a reply') // her second message is unanswered
            ->call('setChatFilter', 'awaiting')
            ->assertSee('Rita');

        $page->call('setTab', 'ads')
            ->assertSet('adPlatform', 'google')
            ->assertSee('Search - Brand')
            ->assertSee('Performance Max')
            ->assertSee('Conversion value')
            ->assertSee('USD 9.64') // cost per conversion: 120.50 / 12.5
            ->call('setBreakdown', 'age')
            ->assertSee('25-34')
            ->call('setGoogleTrend', 'conversions')
            ->assertSee('Conversions per day');

        $page->call('setTab', 'sms')
            ->assertSee('All messages')
            ->assertSee('+96170123456')
            ->assertSee('Lebanon')
            ->assertSee('33.3%') // 1 of 3 delivered
            ->call('setSmsStatus', 'failed')
            ->assertSee('+971501234567')
            ->assertDontSee('+96170123456');
    }

    public function test_selecting_an_account_opens_its_tab(): void
    {
        ['google' => $google, 'bsb' => $bsb] = $this->seedAccounts();

        Livewire::actingAs($this->user)->test(SocialMediaView::class)
            ->call('setAccount', $bsb->id)->assertSet('tab', 'sms')
            ->call('setAccount', $google->id)->assertSet('tab', 'ads')->assertSet('adPlatform', 'google')->assertSet('breakdown', 'age');
    }

    public function test_connect_bsb_checks_credentials_and_starts_a_fetch(): void
    {
        Bus::fake();
        Http::fake([
            '*/getBalanceJson.php' => Http::sequence()
                ->push(['status' => 300, 'message' => 'Invalid API key or secret'])
                ->push(['status' => 201, 'smsbalance' => '99', 'wpbalance' => '5']),
        ]);

        $page = Livewire::actingAs($this->user)->test(SocialMediaView::class)
            ->call('openBsbConnect')
            ->set('bsbName', 'Promo line')
            ->set('bsbKey', ' wrong ')
            ->set('bsbSecret', 'nope')
            ->call('connectBsb')
            ->assertHasErrors(['bsbKey']);
        $this->assertSame(0, SocialAccount::where('platform', 'bsb')->count());

        $page->set('bsbKey', 'key-1')->set('bsbSecret', 'secret-1')->call('connectBsb')
            ->assertHasNoErrors()
            ->assertSet('showBsbConnect', false)
            ->assertSet('tab', 'sms');

        $account = SocialAccount::where('platform', 'bsb')->firstOrFail();
        $this->assertSame('Promo line', $account->name);
        $this->assertSame(['api_key' => 'key-1', 'api_secret' => 'secret-1'], $account->bsbCredentials());
        $this->assertStringNotContainsString('key-1', $account->external_id);
        $this->assertEquals(99, $account->meta['sms_balance']);
        Bus::assertDispatchedAfterResponse(SyncSocialAccounts::class, fn ($job) => $job->accountIds === [$account->id]);
    }
}
