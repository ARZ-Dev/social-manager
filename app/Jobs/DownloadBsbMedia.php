<?php

namespace App\Jobs;

use App\Models\Social\SocialMessage;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Saves the photo / voice note / video / document of an incoming WhatsApp
 * message. BSB's download link expires after 7 days, so the file is kept on
 * the private "local" disk and served through SocialMessageMediaController.
 *
 * Dispatched with dispatchAfterResponse() by the webhook. Only links on BSB's
 * own HTTPS domain are fetched, so a forged webhook can't make the server
 * download from anywhere else.
 */
class DownloadBsbMedia
{
    use Dispatchable;

    public function __construct(public int $messageId)
    {
    }

    public function handle(): void
    {
        $message = SocialMessage::with('conversation')->find($this->messageId);
        $media = $message?->media ?? [];
        $url = $media['download_url'] ?? null;
        if (!$message || !$url || !empty($media['path']) || !self::allowedUrl($url)) {
            return;
        }

        $max = (int) config('bsb.media_max_mb') * 1024 * 1024;
        if (!empty($media['file_size']) && $media['file_size'] > $max) {
            return; // too big to keep: the link still works for 7 days
        }

        try {
            $response = Http::timeout(60)->connectTimeout(15)->get($url);
        } catch (\Throwable $e) {
            Log::warning('BSB media download failed', ['message' => $message->id, 'error' => $e->getMessage()]);
            return;
        }

        $body = $response->body();
        if (!$response->successful() || $body === '' || strlen($body) > $max) {
            Log::warning('BSB media download refused', ['message' => $message->id, 'status' => $response->status(), 'bytes' => strlen($body)]);
            return;
        }

        $path = SocialMessage::MEDIA_DIR . '/' . $message->conversation->social_account_id . '/' . $message->id . '.' . self::extension($media);
        Storage::disk('local')->put($path, $body);

        $message->update(['media' => array_merge($media, ['path' => $path])]);
    }

    public static function allowedUrl(string $url): bool
    {
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');

        return ($parts['scheme'] ?? '') === 'https'
            && ($host === 'bestsmsbulk.com' || str_ends_with($host, '.bestsmsbulk.com'));
    }

    /** File extension from the filename, else from the type. Letters and digits only. */
    protected static function extension(array $media): string
    {
        $ext = strtolower(pathinfo((string) ($media['filename'] ?? ''), PATHINFO_EXTENSION));
        if (preg_match('/^[a-z0-9]{1,8}$/', $ext)) {
            return $ext;
        }

        return [
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'audio/ogg' => 'ogg',
            'audio/mpeg' => 'mp3', 'audio/mp4' => 'm4a', 'video/mp4' => 'mp4', 'application/pdf' => 'pdf',
        ][strtolower(explode(';', (string) ($media['mime_type'] ?? ''))[0])] ?? 'bin';
    }
}
