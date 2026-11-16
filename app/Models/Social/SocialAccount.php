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
