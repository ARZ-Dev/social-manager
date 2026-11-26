<?php

namespace App\Models\Social;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * An account linked to a company: a Facebook Page, Instagram business account,
 * Meta ad account, Google Ads account or BSB (SMS / WhatsApp) account.
 */
class SocialAccount extends Model
{
    public const FACEBOOK = 'facebook';
    public const INSTAGRAM = 'instagram';
    public const ADS = 'ads'; // Meta ad account
    public const GOOGLE_ADS = 'google_ads';
    public const BSB = 'bsb';

    /** Display and fetch order: Pages before their Instagram accounts, then ad accounts, then BSB. */
    public const PLATFORMS = [self::FACEBOOK, self::INSTAGRAM, self::ADS, self::GOOGLE_ADS, self::BSB];

    /** Platforms with posts, audience and conversations. */
    public const SOCIAL = [self::FACEBOOK, self::INSTAGRAM];

    /** Ad accounts, shown in the Ads tab. */
    public const AD_PLATFORMS = [self::ADS, self::GOOGLE_ADS];

    /** Platforms connected through the company's Meta app. */
    public const META = [self::FACEBOOK, self::INSTAGRAM, self::ADS];

    protected $guarded = [];

    protected $hidden = ['access_token', 'webhook_secret'];

    /** Periods offered in the Fetch dialog: value => label (days, or 'custom'). */
    public const FETCH_PERIODS = ['7' => '7 days', '30' => '30 days', '90' => '90 days', '180' => '6 months', '365' => '1 year', 'custom' => 'Custom'];

    /** How far back a custom fetch may start (Meta keeps about 2 years of insights). */
    public const FETCH_MAX_DAYS = 730;

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'webhook_secret' => 'encrypted',
            'webhook_last_at' => 'datetime',
            'token_expires_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'sync_queued_at' => 'datetime',
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
            $account->adDailyStats()->delete();
            $account->bulkMessages()->delete();
            Storage::disk('local')->deleteDirectory(SocialMessage::MEDIA_DIR . '/' . $account->id);
        });
    }

    /** Sort accounts in PLATFORMS order (Pages, Instagram, Meta ads, Google Ads, BSB). */
    public function scopeOrderedByPlatform($query)
    {
        $list = implode(',', array_map(fn ($p) => "'$p'", self::PLATFORMS));

        return $query->orderByRaw("FIELD(platform, $list)");
    }

    /**
     * BSB API key and secret. Both are kept encrypted in access_token; the
     * external_id is only a fingerprint of the key, so it can't be read back.
     *
     * @return array{api_key: string, api_secret: string}|null
     */
    public function bsbCredentials(): ?array
    {
        if ($this->platform !== self::BSB || !$this->access_token) {
            return null;
        }

        $creds = json_decode($this->access_token, true);

        return is_array($creds) && filled($creds['api_key'] ?? null) && filled($creds['api_secret'] ?? null) ? $creds : null;
    }

    /**
     * The URL BSB posts this account's WhatsApp messages to (created on first use).
     * BSB's payload doesn't say which account it's for, so the URL does.
     */
    public function bsbWebhookUrl(): string
    {
        if (!$this->webhook_key) {
            $this->forceFill(['webhook_key' => Str::random(40)])->save();
        }

        return route('webhooks.bsb', $this->webhook_key);
    }

    /** external_id for a BSB account: a short fingerprint of its API key. */
    public static function bsbFingerprint(string $apiKey): string
    {
        return 'bsb_' . substr(hash('sha256', $apiKey), 0, 24);
    }

    /** "@username" for Instagram, the Page / ad account name otherwise. */
    public function getDisplayNameAttribute(): string
    {
        if ($this->platform === self::INSTAGRAM && $this->username) {
            return '@' . $this->username;
        }

        return $this->name ?: $this->external_id;
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

    public function adDailyStats(): HasMany
    {
        return $this->hasMany(SocialAdDailyStat::class);
    }

    public function bulkMessages(): HasMany
    {
        return $this->hasMany(SocialBulkMessage::class);
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
