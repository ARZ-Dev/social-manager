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

    public function test_reaction_keeps_just_the_emoji_and_is_not_a_file(): void
    {
        Http::fake();
        $reaction = $this->textEvent('wamid.R1', '2026-07-01T12:00:00+00:00', "👍 *Reaction Received*: 👍\n🔗 *Reacted to Message ID*: wamid.HBgLOTYxMzI1ODI4MhUCABEYEjEyQTlEMzI4OEZCRkMwNTE2MgA=");
        $reaction['data']['type'] = 'reaction';
        $this->deliver($reaction)->assertOk()->assertJson(['result' => 'stored']);

        $message = SocialMessage::sole();
        $this->assertSame('reaction', $message->type);
        $this->assertSame('👍', $message->message);
        $this->assertNull($message->media);
        $this->assertFalse($message->hasFile());
        $this->assertSame('Reacted 👍', SocialConversation::sole()->snippet);
        Http::assertNothingSent();

        // Removing a reaction arrives with no emoji.
        $removed = $this->textEvent('wamid.R2', '2026-07-01T12:01:00+00:00', "👍 *Reaction Received*: \n🔗 *Reacted to Message ID*: wamid.X");
        $removed['data']['type'] = 'reaction';
        $this->deliver($removed)->assertOk();
        $this->assertNull(SocialMessage::where('external_id', 'wamid.R2')->value('message'));
        $this->assertSame('Removed a reaction', SocialConversation::sole()->snippet);
    }

    public function test_older_reactions_are_tidied_by_the_migration(): void
    {
        $conversation = SocialConversation::create([
            'social_account_id' => $this->account->id, 'external_id' => 'wa:96170123456', 'participant_name' => 'Karim',
            'snippet' => '📄 👍 Reaction Received: 👍 🔗 Reacted to Message ID: wamid.X',
        ]);
        $message = $conversation->messages()->create([
            'external_id' => 'wamid.OLD', 'from_page' => false, 'type' => 'reaction', 'media' => [],
            'message' => "👍 *Reaction Received*: 👍\n🔗 *Reacted to Message ID*: wamid.X", 'sent_at' => now(),
        ]);

        (require database_path('migrations/2026_11_26_000002_tidy_bsb_whatsapp_reactions.php'))->up();

        $this->assertSame('👍', $message->fresh()->message);
        $this->assertNull($message->fresh()->media);
        $this->assertSame('Reacted 👍', $conversation->fresh()->snippet);
    }

    public function test_message_to_a_number_that_never_wrote_starts_a_chat(): void
    {
        // An order confirmation: only its statuses arrive.
        $this->deliver($this->statusEvent('wamid.ORDER', 'delivered', '2026-07-01T12:00:00+00:00', null, '96171999999'))
            ->assertOk()->assertJson(['result' => 'status']);

        $conversation = SocialConversation::sole();
        $this->assertSame('wa:96171999999', $conversation->external_id);
        $this->assertSame('+96171999999', $conversation->participant_name);
        $this->assertSame('Sent from BSB · Delivered', $conversation->snippet);
        $this->assertSame('2026-07-01 12:00:00', $conversation->last_message_at->utc()->toDateTimeString());
        $this->assertTrue(SocialMessage::sole()->from_page);

        $this->deliver($this->statusEvent('wamid.ORDER', 'read', '2026-07-01T12:05:00+00:00', null, '96171999999'))->assertOk();
        $this->assertSame('Sent from BSB · Read', $conversation->fresh()->snippet);

        // The customer answers: the chat takes their name, their message is the latest.
        $reply = $this->textEvent('wamid.ANSWER', '2026-07-01T12:30:00+00:00', 'Thanks! When will it arrive?');
        $reply['data']['from'] = '96171999999';
        $this->deliver($reply)->assertOk();
        $conversation->refresh();
        $this->assertSame('Jane', $conversation->participant_name);
        $this->assertSame('Thanks! When will it arrive?', $conversation->snippet);

        // A late status of the older message doesn't take the preview back.
        $this->deliver($this->statusEvent('wamid.ORDER', 'sent', '2026-07-01T11:59:58+00:00', null, '96171999999'))->assertOk();
        $this->assertSame('Thanks! When will it arrive?', $conversation->fresh()->snippet);
        $this->assertSame(1, SocialConversation::count());
    }

    public function test_switched_off_account_drops_deliveries(): void
    {
        $this->account->update(['webhook_enabled' => false]);

        $this->deliver($this->textEvent())->assertOk()->assertJson(['ok' => true, 'result' => 'disabled']);
        $this->deliver($this->statusEvent('wamid.ORDER', 'delivered', '2026-07-01T12:00:00+00:00'))->assertOk()->assertJson(['result' => 'disabled']);

        $this->assertSame(0, SocialConversation::count());
        $this->assertNull($this->account->fresh()->webhook_last_at);

        $this->account->update(['webhook_enabled' => true]);
        $this->deliver($this->textEvent())->assertOk()->assertJson(['result' => 'stored']);
    }

    public function test_status_without_a_number_for_an_unknown_message_is_ignored(): void
    {
        $this->deliver($this->statusEvent('wamid.X', 'delivered', '2026-07-01T12:00:00+00:00', null, ''))
            ->assertOk()->assertJson(['result' => 'ignored']);

        $this->assertSame(0, SocialConversation::count());
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

        $insider = $user($this->account->company_id, 'insider');
        $this->actingAs($insider)
            ->get(route('social.media', $message))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        // Hidden while the account's WhatsApp chats are off.
        $this->account->update(['webhook_enabled' => false]);
        $this->actingAs($insider)->get(route('social.media', $message))->assertNotFound();
        $this->account->update(['webhook_enabled' => true]);

        $other = Company::create(['name' => 'Other']);
        $this->actingAs($user($other->id, 'outsider'))->get(route('social.media', $message))->assertNotFound();
    }
}
