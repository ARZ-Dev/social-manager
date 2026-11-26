<?php

namespace App\Http\Controllers;

use App\Models\Social\SocialAccount;
use App\Models\Social\SocialMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * The file of a WhatsApp chat message (photo, voice note, video, document),
 * for people allowed to see the account's data. Served from the copy kept by
 * DownloadBsbMedia, or BSB's own link while it's still valid.
 */
class SocialMessageMediaController extends Controller
{
    /** Types safe to show in the page. Anything else (HTML, SVG...) is downloaded, never rendered. */
    protected const INLINE = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    public function show(Request $request, SocialMessage $message)
    {
        abort_unless($request->user()->can('social-list'), 403);

        $message->loadMissing('conversation');
        $visible = SocialAccount::visibleTo($request->user())->whereKey($message->conversation?->social_account_id)->exists();
        abort_unless($visible, 404);

        $media = $message->media ?? [];
        $mime = strtolower(explode(';', (string) ($media['mime_type'] ?? 'application/octet-stream'))[0]);
        $inline = in_array($mime, self::INLINE, true) || str_starts_with($mime, 'audio/') || str_starts_with($mime, 'video/');
        $name = basename((string) ($media['filename'] ?? '')) ?: 'whatsapp-' . $message->id;

        if (!empty($media['path']) && Storage::disk('local')->exists($media['path'])) {
            return Storage::disk('local')->response($media['path'], $name, [
                'Content-Type' => $inline ? $mime : 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, max-age=86400',
            ], $inline ? 'inline' : 'attachment');
        }

        // Not downloaded (yet): BSB's signed link works for 7 days.
        $expires = isset($media['expires_at']) ? Carbon::parse($media['expires_at']) : null;
        if (!empty($media['download_url']) && (!$expires || $expires->isFuture())) {
            return redirect()->away($media['download_url']);
        }

        abort(404, 'This file is no longer available.');
    }
}
