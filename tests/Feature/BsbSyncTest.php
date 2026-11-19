<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Social\SocialAccount;
use App\Models\Social\SocialBulkMessage;
use App\Services\Bsb\BsbSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BsbSyncTest extends TestCase
{
    use RefreshDatabase;

    /** Today's SMS list BSB returns; tests change it between runs. */
    protected array $smsToday = [];

    /** Today's WhatsApp list; empty = BSB's "No msgs sent today". */
    protected array $wpToday = [];

    protected function account(): SocialAccount
    {
        $company = Company::create(['name' => 'Acme']);

        return SocialAccount::create([
            'company_id' => $company->id,
            'platform' => SocialAccount::BSB,
            'external_id' => SocialAccount::bsbFingerprint('key-1'),
            'name' => 'Main',
            'access_token' => json_encode(['api_key' => 'key-1', 'api_secret' => 'secret-1']),
        ])->load('company');
    }

    protected function fakeBsb(): void
    {
        Http::fake(function (Request $request) {
            $body = $request->data();
            $path = parse_url($request->url(), PHP_URL_PATH);

            return match (true) {
                str_ends_with($path, '/getBalanceJson.php') => Http::response(['status' => 201, 'message' => 'Balance retrieved', 'smsbalance' => '1250.50', 'wpbalance' => '30']),
                str_ends_with($path, '/getMessageStatusJson.php') => ($body[0]['route'] ?? 'sms') === 'sms'
                    ? Http::response($this->smsToday)
                    : ($this->wpToday ? Http::response($this->wpToday) : Http::response('No msgs sent today')), // plain text answer
                str_ends_with($path, '/getMessageStatusByAcksJson.php') => Http::response(array_map(fn ($ack) => $ack === 50
                    ? ['ack' => 50, 'opstatus' => 'FAILED', 'recipient' => '96171000000', 'found' => true]
                    : ($ack === 60
                    ? ['ack' => 60, 'opstatus' => 'READ', 'recipient' => '96181800453', 'found' => true]
                    : ['ack' => $ack, 'opstatus' => null, 'recipient' => null, 'found' => false]), $body['acks'])),
                default => Http::response('not found', 404),
            };
        });
    }

    public function test_sync_logs_todays_messages_balance_and_rechecks_open_ones(): void
    {
        $account = $this->account();
        // Seen two days ago and still pending: re-checked by ID.
        SocialBulkMessage::create([
            'social_account_id' => $account->id, 'route' => 'sms', 'ack' => '50', 'recipient' => '96171000000',
            'status' => 'PENDING', 'first_seen_at' => now()->subDays(2), 'created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2),
        ]);
        $this->smsToday = [
            ['ack' => '101', 'opstatus' => 'DELIVERED', 'recipient' => '96170123456'],
            ['ack' => '102', 'opstatus' => 'pending', 'recipient' => '971501234567'],
        ];
        $this->fakeBsb();

        $result = app(BsbSyncService::class)->syncAccount($account);
        $account->refresh();

        $this->assertSame([], $result['warnings']);
        $this->assertNull($account->last_sync_error);
        $this->assertEquals(1250.5, $account->meta['sms_balance']);
        $this->assertEquals(30, $account->meta['wp_balance']);

        $messages = SocialBulkMessage::where('social_account_id', $account->id)->get()->keyBy('ack');
        $this->assertCount(3, $messages);
        $this->assertSame('DELIVERED', $messages['101']->status);
        $this->assertNotNull($messages['101']->delivered_at);
        $this->assertSame('LB', $messages['101']->country);
        $this->assertSame('PENDING', $messages['102']->status);
        $this->assertSame('AE', $messages['102']->country);
        $this->assertSame('FAILED', $messages['50']->status); // from the re-check
        $this->assertSame(2, $result['counts']['new messages']);

        // Next check: 102 got delivered. Nothing is duplicated.
        $this->smsToday[1]['opstatus'] = 'DELIVERED';
        $this->travel(10)->minutes();
        $result = app(BsbSyncService::class)->syncAccount($account->fresh()->load('company'));

        $this->assertSame(3, SocialBulkMessage::where('social_account_id', $account->id)->count());
        $m = SocialBulkMessage::where('ack', '102')->first();
        $this->assertSame('DELIVERED', $m->status);
        $this->assertNotNull($m->delivered_at);
        $this->assertTrue($m->status_changed_at->gt($m->first_seen_at));
        $this->assertSame(0, $result['counts']['new messages']);
        $this->assertSame(1, $result['counts']['status updates']);
    }

    public function test_whatsapp_read_counts_as_delivered_and_delivered_whatsapp_is_rechecked(): void
    {
        $account = $this->account();
        $old = fn (string $route, string $ack) => SocialBulkMessage::create([
            'social_account_id' => $account->id, 'route' => $route, 'ack' => $ack, 'recipient' => '96181800453', 'status' => 'DELIVERED',
            'first_seen_at' => now()->subDays(2), 'delivered_at' => now()->subDays(2), 'created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2),
        ]);
        $old('wp', '60');  // delivered WhatsApp: may still be read, so re-checked
        $old('sms', '70'); // delivered SMS: final, left alone
        $this->wpToday = [['ack' => '201', 'opstatus' => 'READ', 'recipient' => '96170611905']];
        $this->fakeBsb();

        app(BsbSyncService::class)->syncAccount($account);

        $today = SocialBulkMessage::where('ack', '201')->first();
        $this->assertSame('READ', $today->status);
        $this->assertNotNull($today->delivered_at);
        $this->assertSame('READ', SocialBulkMessage::where('ack', '60')->value('status'));
        $this->assertTrue(SocialBulkMessage::where('ack', '60')->first()->delivered_at->lt(now()->subDay())); // first delivery kept
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/getMessageStatusByAcksJson.php') && $r['acks'] === [60]);
    }

    public function test_refused_credentials_become_a_warning(): void
    {
        $account = $this->account();
        Http::fake(fn () => Http::response(['status' => 300, 'message' => 'Invalid API key or secret']));

        $result = app(BsbSyncService::class)->syncAccount($account);

        $this->assertStringContainsString('Invalid API key or secret', $account->fresh()->last_sync_error);
        $this->assertNotEmpty($result['warnings']);
        $this->assertSame(0, SocialBulkMessage::count());
    }

    public function test_poll_command_checks_every_bsb_account(): void
    {
        $account = $this->account();
        $this->smsToday = [['ack' => '7', 'opstatus' => 'SENT', 'recipient' => '96170111222']];
        $this->fakeBsb();

        $this->artisan('bsb:poll')->assertSuccessful();

        $this->assertSame(1, SocialBulkMessage::where('social_account_id', $account->id)->count());
        $this->assertNotNull($account->fresh()->last_synced_at);
    }
}
