<?php

namespace App\Http\Controllers;

use App\Jobs\DownloadBsbMedia;
use App\Models\Social\SocialAccount;
use App\Services\Bsb\BsbWebhookHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receives BSB's WhatsApp Messaging API webhook (incoming messages and status
 * changes). Each BSB account has its own URL: /api/webhooks/bsb/{webhook_key}.
 *
 * Once the account's signing secret is saved, every delivery must carry
 * X-BSB-Signature: sha256=<HMAC-SHA256 of the raw body with that secret>.
 * Before that, the unguessable URL is the only check.
 */
class BsbWebhookController extends Controller
{
    public function __invoke(Request $request, string $key, BsbWebhookHandler $handler)
    {
        $account = SocialAccount::where('platform', SocialAccount::BSB)->where('webhook_key', $key)->first();
        if (!$account) {
            return response()->json(['ok' => false, 'error' => 'Unknown webhook URL'], 404);
        }
        // Switched off in the app: accept and drop, so BSB doesn't count failures and retry.
        if (!$account->whatsAppChatsOn()) {
            return response()->json(['ok' => true, 'result' => 'disabled']);
        }

        $raw = $request->getContent();
        if (filled($account->webhook_secret) && !$this->signatureValid($raw, (string) $request->header('X-BSB-Signature'), $account->webhook_secret)) {
            Log::warning('BSB webhook: invalid signature', ['social_account_id' => $account->id, 'event' => $request->json('event')]);
            return response()->json(['ok' => false, 'error' => 'Invalid signature'], 401);
        }

        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            return response()->json(['ok' => false, 'error' => 'Expected a JSON body'], 400);
        }

        $result = $handler->handle($account, $payload);

        // For the setup screen. A plain update of these two columns only, so it can't
        // overwrite what the balance check writes to the account at the same moment.
        SocialAccount::whereKey($account->id)->update([
            'webhook_last_at' => now(),
            'webhook_last_event' => mb_substr((string) ($payload['event'] ?? 'unknown'), 0, 40),
        ]);

        // Fetch media after answering, so BSB isn't kept waiting.
        if ($result['download']) {
            DownloadBsbMedia::dispatchAfterResponse($result['download']);
        }

        return response()->json(['ok' => true, 'result' => $result['result']]);
    }

    /** "sha256=<hex>" (or the bare hex) must match the HMAC of the raw body. */
    protected function signatureValid(string $raw, string $header, string $secret): bool
    {
        $given = strtolower(trim($header));
        if (str_starts_with($given, 'sha256=')) {
            $given = substr($given, 7);
        }

        return $given !== '' && hash_equals(hash_hmac('sha256', $raw, $secret), $given);
    }
}
