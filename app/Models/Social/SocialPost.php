<?php

namespace App\Models\Social;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SocialPost extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'posted_at' => 'datetime',
            'insights' => 'array',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class, 'social_account_id');
    }

    /** Shares: Facebook sends them with the post, Instagram only in its insights. */
    public function getSharesTotalAttribute(): int
    {
        return (int) ($this->share_count ?: ($this->insights['shares'] ?? 0));
    }

    /** Likes / reactions + comments + shares. */
    public function getEngagementAttribute(): int
    {
        return (int) $this->like_count + (int) $this->comment_count + $this->shares_total;
    }

    /** People reached, from whichever reach / views metric Meta returned. */
    public function getReachAttribute(): ?int
    {
        foreach (['post_total_media_view_unique', 'post_impressions_unique', 'reach', 'views'] as $metric) {
            if (isset($this->insights[$metric])) {
                return (int) $this->insights[$metric];
            }
        }

        return null;
    }

    /** Short readable post type, e.g. "Reel", "Video", "Photo". */
    public function getTypeLabelAttribute(): ?string
    {
        return match (strtolower((string) $this->media_type)) {
            '' => null,
            'reels' => 'Reel',
            'story' => 'Story',
            'feed', 'image' => 'Post',
            'video', 'added_video' => 'Video',
            'added_photos' => 'Photo',
            'carousel_album' => 'Carousel',
            'shared_story' => 'Shared',
            'mobile_status_update', 'wall_post' => 'Text',
            default => ucfirst(str_replace('_', ' ', strtolower($this->media_type))),
        };
    }

    public function comments(): HasMany
    {
        return $this->hasMany(SocialComment::class);
    }
}
