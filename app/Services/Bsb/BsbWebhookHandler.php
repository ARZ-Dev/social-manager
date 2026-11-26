<?php

namespace App\Services\Bsb;

use App\Models\Social\SocialAccount;
use App\Models\Social\SocialConversation;
use App\Models\Social\SocialMessage;
use Illuminate\Support\Carbon;

/**
 * Stores what BSB's WhatsApp Messaging API webhook sends:
 *
 * - message.received: a customer's WhatsApp message (text, image, video, voice,
 *   document). Starts or continues the conversation with that number.
 * - message.status: an outgoing message was sent / delivered / read / failed.
 *   BSB doesn't include the text, so the message is stored without it. Only
 *   kept for numbers that already wrote to the business, so bulk campaigns
 *   don't fill the inbox with one-way threads.
 *
 * Deliveries can repeat and arrive out of order: everything is keyed on the
 * WhatsApp message ID, and statuses never move backwards.
 */
class BsbWebhookHandler
{
    /** Conversation external_id prefix: these are WhatsApp chats of the BSB account. */
    public const PREFIX = 'wa:';

    /**
     * @return array{result: string, download: ?int} download: a message whose media should be fetched
     */
    public function handle(SocialAccount $account, array $payload): array
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $at = $this->time($payload['timestamp'] ?? null);

        return match ((string) ($payload['event'] ?? '')) {
            'message.received' => $this->received($account, $data, $at),
            'message.status' => $this->status($account, $data, $at),
            default => ['result' => 'ignored', 'download' => null], // e.g. BSB's "Send test"
        };
    }

    protected function received(SocialAccount $account, array $data, Carbon $at): array
    {
        $from = trim((string) ($data['from'] ?? ''));
        $messageId = trim((string) ($data['message_id'] ?? ''));
        if ($from === '' || $messageId === '') {
            return ['result' => 'ignored', 'download' => null];
        }

        $name = trim((string) ($data['profile_name'] ?? ''));
        $conversation = SocialConversation::firstOrCreate(
            ['social_account_id' => $account->id, 'external_id' => mb_substr(self::PREFIX . $from, 0, 191)],
            ['participant_name' => $name ?: $this->displayNumber($from)]
        );

        $type = strtolower(trim((string) ($data['type'] ?? 'text'))) ?: 'text';
        $text = trim((string) ($data['message'] ?? ''));
        $existing = SocialMessage::where('social_conversation_id', $conversation->id)->where('external_id', $messageId)->first();

        $media = null;
        if ($type !== 'text' || !empty($data['media_download_url'])) {
            $media = array_filter([
                'mime_type' => $data['mime_type'] ?? null,
                'filename' => isset($data['filename']) ? mb_substr((string) $data['filename'], 0, 200) : null,
                'file_size' => isset($data['file_size']) ? (int) $data['file_size'] : null,
                'voice' => !empty($data['is_voice_note']),
                'download_url' => $data['media_download_url'] ?? null,
                'expires_at' => $data['media_download_expires_at'] ?? null,
                // A repeated delivery keeps the file already downloaded.
                'path' => $existing?->media['path'] ?? null,
            ], fn ($v) => $v !== null && $v !== false);
        }

        $message = SocialMessage::updateOrCreate(
            ['social_conversation_id' => $conversation->id, 'external_id' => $messageId],
            [
                'from_name' => $name ?: null,
                'from_id' => mb_substr($from, 0, 191),
                'from_page' => false,
                'message' => $text !== '' ? $text : null,
                'type' => mb_substr($type, 0, 20),
                'media' => $media,
                'sent_at' => $existing?->sent_at ?? $at,
            ]
        );

        // A late or repeated delivery of an older message doesn't replace the preview.
        $isLatest = !$conversation->last_message_at || $conversation->last_message_at->lte($at);
        $conversation->update(array_filter([
            'participant_name' => $name ?: null, // the customer may have changed it
            'snippet' => $isLatest ? $this->preview($message) : null,
            'last_message_at' => $isLatest ? $at : null,
        ]));

        $download = !empty($media['download_url']) && empty($media['path']) ? $message->id : null;

        return ['result' => $existing ? 'updated' : 'stored', 'download' => $download];
    }

    protected function status(SocialAccount $account, array $data, Carbon $at): array
    {
        $messageId = trim((string) ($data['message_id'] ?? ''));
        $contact = trim((string) ($data['contact'] ?? $data['to'] ?? ''));
        $status = strtolower(trim((string) ($data['status'] ?? '')));
        if ($messageId === '' || !isset(SocialMessage::STATUS_RANK[$status])) {
            return ['result' => 'ignored', 'download' => null];
        }

        $message = SocialMessage::where('external_id', $messageId)
            ->whereHas('conversation', fn ($q) => $q->where('social_account_id', $account->id))
            ->first();

        if (!$message) {
            $conversation = $contact === '' ? null : SocialConversation::where('social_account_id', $account->id)
                ->where('external_id', self::PREFIX . $contact)
                ->first();
            if (!$conversation) {
                return ['result' => 'ignored', 'download' => null]; // nobody we chat with: a campaign message
            }

            $message = SocialMessage::firstOrCreate(
                ['social_conversation_id' => $conversation->id, 'external_id' => $messageId],
                ['from_page' => true, 'sent_at' => $at]
            );
            if ($conversation->last_message_at === null || $conversation->last_message_at->lt($at)) {
                $conversation->update(['last_message_at' => $at]);
            }
        }

        $current = SocialMessage::STATUS_RANK[$message->status] ?? 0;
        $changes = [
            // The earliest event is the closest we get to when it was sent.
            'sent_at' => $message->sent_at && $message->sent_at->lte($at) ? $message->sent_at : $at,
        ];
        if ($status === 'failed' || SocialMessage::STATUS_RANK[$status] > $current) {
            $changes['status'] = $status;
            $changes['error'] = $status === 'failed' ? $this->error($data['error'] ?? null) : null;
        }
        $message->update($changes);

        return ['result' => 'status', 'download' => null];
    }

    /** BSB's ISO 8601 timestamp, or now when it's missing (its voice example has none). */
    protected function time($value): Carbon
    {
        try {
            return $value ? Carbon::parse($value)->setTimezone(config('app.timezone')) : now();
        } catch (\Throwable) {
            return now();
        }
    }

    /** A failure reason as text, whether BSB sends a string or an object. */
    protected function error($error): ?string
    {
        if (is_array($error)) {
            $error = $error['message'] ?? $error['title'] ?? $error['error_data']['details'] ?? json_encode($error);
        }

        return $error === null || $error === '' ? null : mb_substr((string) $error, 0, 500);
    }

    /** Conversation preview: the text, or what kind of file it was. */
    protected function preview(SocialMessage $message): string
    {
        $text = trim(str_replace('*', '', (string) $message->message));
        if ($message->type === 'text') {
            return mb_substr($text, 0, 500);
        }

        $icon = ['audio' => '🎤', 'image' => '📷', 'video' => '🎬'][$message->type] ?? '📄';
        // BSB fills voice notes with "🎤 *Voice Message*": use our own label, or the caption of a photo / video.
        $caption = $message->type === 'audio' ? '' : $text;

        return mb_substr($icon . ' ' . ($caption !== '' ? $caption : $message->mediaLabel()), 0, 500);
    }

    protected function displayNumber(string $from): string
    {
        return ctype_digit($from) ? '+' . $from : $from;
    }
}
