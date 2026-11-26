<?php

namespace App\Models\Social;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class SocialMessage extends Model
{
    /** Where downloaded WhatsApp media is kept, per account, on the private "local" disk. */
    public const MEDIA_DIR = 'bsb-media';

    /** Order of outgoing statuses: a later webhook never moves a message back. failed wins. */
    public const STATUS_RANK = ['sent' => 1, 'delivered' => 2, 'read' => 3, 'failed' => 9];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'from_page' => 'boolean',
            'media' => 'array',
        ];
    }

    /**
     * Whether the attached file can be shown: downloaded, or BSB's link hasn't expired yet.
     */
    public function hasViewableMedia(): bool
    {
        $media = $this->media ?? [];
        if (!empty($media['path']) && Storage::disk('local')->exists($media['path'])) {
            return true;
        }

        return !empty($media['download_url'])
            && (empty($media['expires_at']) || now()->lt(Carbon::parse($media['expires_at'])));
    }

    /** "Voice message", "Photo", "invoice.pdf"... for previews and file links. */
    public function mediaLabel(): string
    {
        $media = $this->media ?? [];

        return match ($this->type) {
            'audio' => !empty($media['voice']) ? 'Voice message' : ($media['filename'] ?? 'Audio'),
            'image' => 'Photo',
            'video' => 'Video',
            'sticker' => 'Sticker',
            default => $media['filename'] ?? ucfirst((string) ($this->type ?: 'File')),
        };
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(SocialConversation::class, 'social_conversation_id');
    }
}
