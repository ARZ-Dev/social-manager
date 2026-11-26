<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Social\SocialAccount;
use App\Models\Social\SocialConversation;
use App\Models\Social\SocialMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * BSB WhatsApp Messaging API webhook, with the payloads from BSB's documentation.
 */
class BsbWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected SocialAccount $account;

    protected function setUp(): void
    {
        parent::setUp();

        $company = Company::create(['name' => 'Acme']);
        $this->account = SocialAccount::create([
            'company_id' => $company->id, 'platform' => SocialAccount::BSB, 'name' => 'Main BSB',
            'external_id' => SocialAccount::bsbFingerprint('k'), 'access_token' => json_encode(['api_key' => 'k', 'api_secret' => 's']),
        ]);
        $this->account->bsbWebhookUrl(); // creates the key
    }

    protected function deliver(array $payload, ?string $secret = null)
    {
        $body = json_encode($payload);
        $headers = ['Content-Type' => 'application/json'];
        if ($secret !== null) {
            $headers['X-BSB-Signature'] = 'sha256=' . hash_hmac('sha256', $body, $secret);
        }

        return $this->call('POST', '/api/webhooks/bsb/' . $this->account->fresh()->webhook_key, [], [], [], $this->transformHeadersToServerVars($headers), $body);
    }

    protected function textEvent(string $id = 'wamid.A1', string $at = '2026-07-01T12:00:00+00:00', string $text = 'Hello'): array
    {
        return ['event' => 'message.received', 'timestamp' => $at, 'data' => [
            'message_id' => $id, 'chat_id' => 12345, 'from' => '96170123456', 'profile_name' => 'Jane',
            'message' => $text, 'type' => 'text', 'direction' => 'incoming',
        ]];
    }

    protected function statusEvent(string $id, string $status, string $at, $error = null, string $contact = '96170123456'): array
    {
        return ['event' => 'message.status', 'timestamp' => $at, 'data' => [
            'message_id' => $id, 'chat_id' => 12346, 'contact' => $contact, 'status' => $status, 'error' => $error, 'direction' => 'outgoing',
        ]];
    }

    public function test_unknown_url_is_refused(): void
    {
        $this->postJson('/api/webhooks/bsb/' . str_repeat('x', 40), $this->textEvent())->assertNotFound();
        $this->assertSame(0, SocialMessage::count());
    }

    public function test_incoming_text_starts_a_conversation(): void
    {
        $this->deliver($this->textEvent())->assertOk()->assertJson(['ok' => true, 'result' => 'stored']);

        $conversation = SocialConversation::where('social_account_id', $this->account->id)->sole();
        $this->assertSame('wa:96170123456', $conversation->external_id);
        $this->assertSame('Jane', $conversation->participant_name);
        $this->assertSame('Hello', $conversation->snippet);

        $message = $conversation->messages()->sole();
        $this->assertFalse($message->from_page);
        $this->assertSame('text', $message->type);
        $this->assertSame('2026-07-01 12:00:00', $message->sent_at->utc()->toDateTimeString());

        $account = $this->account->fresh();
        $this->assertNotNull($account->webhook_last_at);
        $this->assertSame('message.received', $account->webhook_last_event);
    }

    public function test_repeated_delivery_is_stored_once(): void
    {
        $this->deliver($this->textEvent())->assertOk();
        $this->deliver($this->textEvent())->assertOk()->assertJson(['result' => 'updated']);

        $this->assertSame(1, SocialMessage::count());
        $this->assertSame(1, SocialConversation::count());
    }

    public function test_signature_is_required_once_the_secret_is_saved(): void
    {
        $this->account->update(['webhook_secret' => 'super-secret-signing-key']);

        $this->deliver($this->textEvent())->assertStatus(401);                     // unsigned
        $this->deliver($this->textEvent(), 'wrong-secret-wrong-secret')->assertStatus(401);
        $this->assertSame(0, SocialMessage::count());

        $this->deliver($this->textEvent(), 'super-secret-signing-key')->assertOk();
        $this->assertSame(1, SocialMessage::count());
    }

    public function test_voice_note_is_downloaded_after_the_response(): void
    {
        Storage::fake('local');
        $url = 'https://www.bestsmsbulk.com/bestsmsbulkapi/whatsappmsging/getMedia.php?chat_id=12345&exp=1&sig=abc';
        Http::fake([$url => Http::response('OggS-fake-audio', 200, ['Content-Type' => 'audio/ogg'])]);

        $this->deliver(['event' => 'message.received', 'data' => [
            'message_id' => 'wamid.V1', 'chat_id' => 12345, 'from' => '96170123456', 'type' => 'audio',
            'message' => '🎤 *Voice Message*', 'is_voice_note' => true, 'mime_type' => 'audio/ogg', 'file_size' => 15,
            'filename' => 'voice_converted_abc.ogg', 'media_download_url' => $url,
            'media_download_expires_at' => now()->addDays(7)->toIso8601String(), 'direction' => 'incoming',
        ]])->assertOk();

        $message = SocialMessage::sole();
        $this->assertSame('audio', $message->type);
        $this->assertTrue($message->media['voice']);
        $this->assertSame('🎤 Voice message', $message->conversation->snippet);
        $this->assertNotNull($message->sent_at); // BSB's voice example has no timestamp
        Storage::disk('local')->assertExists($message->media['path']);
        $this->assertSame('OggS-fake-audio', Storage::disk('local')->get($message->media['path']));
        $this->assertStringEndsWith('.ogg', $message->media['path']);
    }

    public function test_media_from_another_host_is_never_fetched(): void
    {
        Storage::fake('local');
        Http::fake();

        $this->deliver(['event' => 'message.received', 'data' => [
            'message_id' => 'wamid.I1', 'from' => '96170123456', 'type' => 'image', 'mime_type' => 'image/jpeg',
            'media_download_url' => 'http://169.254.169.254/latest/meta-data', 'direction' => 'incoming',
        ]])->assertOk();

        Http::assertNothingSent();
        $this->assertArrayNotHasKey('path', SocialMessage::sole()->media);
    }

    public function test_status_updates_outgoing_messages_and_never_goes_back(): void
    {
        $this->deliver($this->textEvent('wamid.IN', '2026-07-01T12:00:00+00:00'))->assertOk();

        // Delivered arrives before sent: the message is created, then sent only moves its time back.
        $this->deliver($this->statusEvent('wamid.OUT', 'delivered', '2026-07-01T12:10:08+00:00'))->assertOk();
        $this->deliver($this->statusEvent('wamid.OUT', 'sent', '2026-07-01T12:10:05+00:00'))->assertOk();
        $out = SocialMessage::where('external_id', 'wamid.OUT')->sole();
        $this->assertTrue($out->from_page);
        $this->assertNull($out->message); // BSB doesn't send the text
        $this->assertSame('delivered', $out->status);
        $this->assertSame('2026-07-01 12:10:05', $out->sent_at->utc()->toDateTimeString());

        $this->deliver($this->statusEvent('wamid.OUT', 'read', '2026-07-01T12:11:00+00:00'))->assertOk();
        $this->assertSame('read', $out->fresh()->status);

        $this->deliver($this->statusEvent('wamid.OUT2', 'failed', '2026-07-01T12:20:00+00:00', ['code' => 131047, 'message' => 'Re-engagement message']))->assertOk();
        $failed = SocialMessage::where('external_id', 'wamid.OUT2')->sole();
        $this->assertSame('failed', $failed->status);
        $this->assertSame('Re-engagement message', $failed->error);

        // Reply 10 minutes after the customer's message: counted by the response times.
        $this->assertSame(2, SocialMessage::where('from_page', true)->count());
    }

    public function test_status_for_a_number_that_never_wrote_is_ignored(): void
    {
        $this->deliver($this->statusEvent('wamid.CAMPAIGN', 'delivered', '2026-07-01T12:00:00+00:00', null, '96171999999'))
            ->assertOk()->assertJson(['result' => 'ignored']);

        $this->assertSame(0, SocialConversation::count());
        $this->assertSame(0, SocialMessage::count());
    }

    public function test_media_is_only_served_to_people_who_see_the_company(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('bsb-media/x/1.jpg', 'jpeg-bytes');
        $this->deliver($this->textEvent())->assertOk();
        $message = SocialMessage::sole();
        $message->update(['type' => 'image', 'media' => ['mime_type' => 'image/jpeg', 'path' => 'bsb-media/x/1.jpg']]);

        Permission::firstOrCreate(['name' => 'social-list', 'guard_name' => 'web']);
        $user = fn (?int $companyId, string $name) => tap(User::create([
            'username' => $name, 'first_name' => 'T', 'last_name' => 'U', 'phone' => '0', 'email' => "$name@example.test",
            'password' => 'password', 'company_id' => $companyId,
        ]))->givePermissionTo('social-list');

        $this->actingAs($user($this->account->company_id, 'insider'))
            ->get(route('social.media', $message))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $other = Company::create(['name' => 'Other']);
        $this->actingAs($user($other->id, 'outsider'))->get(route('social.media', $message))->assertNotFound();
    }
}
