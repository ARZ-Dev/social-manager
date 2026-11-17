<?php

namespace App\Models\Social;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A Facebook Page, Instagram business account or ad account linked to a company.
 */
class SocialAccount extends Model
{
    public const FACEBOOK = 'facebook';
    public const INSTAGRAM = 'instagram';
    public const ADS = 'ads';

    protected $guarded = [];

    protected $hidden = ['access_token'];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    /**
     * Remove fetched data in code too: the database's foreign-key cascades
     * don't run on MyISAM tables.
     */
    protected static function booted(): void
    {
        static::deleting(function (SocialAccount $account) {
            self::where('parent_id', $account->id)->get()->each->delete();

            $postIds = $account->posts()->pluck('id');
            SocialComment::whereIn('social_post_id', $postIds)->delete();
            $account->posts()->delete();

            $convIds = $account->conversations()->pluck('id');
            SocialMessage::whereIn('social_conversation_id', $convIds)->delete();
            $account->conversations()->delete();

            $account->insights()->delete();
            $account->demographics()->delete();
            $account->ads()->delete();
            $account->adBreakdowns()->delete();
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(SocialPost::class);
    }

    public function insights(): HasMany
    {
        return $this->hasMany(SocialInsight::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(SocialConversation::class);
    }

    public function ads(): HasMany
    {
        return $this->hasMany(SocialAd::class);
    }

    public function demographics(): HasMany
    {
        return $this->hasMany(SocialDemographic::class);
    }

    public function adBreakdowns(): HasMany
    {
        return $this->hasMany(SocialAdBreakdown::class);
    }

    /**
     * Limit accounts to the viewer's company (no limit for Super Admins).
     */
    public function scopeVisibleTo($query, User $viewer)
    {
        if ($viewer->canAccessAllCompanies()) {
            return $query;
        }

        return $query->where('company_id', $viewer->company_id ?? 0);
    }
}
