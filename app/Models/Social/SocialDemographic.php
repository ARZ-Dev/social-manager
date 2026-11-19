<?php

namespace App\Models\Social;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One audience segment of a Page / Instagram account (e.g. followers aged 25-34).
 */
class SocialDemographic extends Model
{
    protected $guarded = [];

    public function account(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class, 'social_account_id');
    }

    /**
     * Readable label for a segment value: country codes become names,
     * gender codes become words, placements lose their underscores.
     */
    public static function label(string $dimension, string $segment): string
    {
        return match ($dimension) {
            'gender' => match (strtolower($segment)) {
                'f', 'female' => 'Women',
                'm', 'male' => 'Men',
                default => 'Unknown',
            },
            'country' => self::countryName($segment),
            'age' => $segment === 'unknown' ? 'Unknown age' : $segment,
            // Google Ads networks
            'network' => [
                'search' => 'Google Search', 'search_partners' => 'Search partners', 'content' => 'Display Network',
                'youtube' => 'YouTube', 'mixed' => 'Cross-network', 'discover' => 'Discover', 'gmail' => 'Gmail',
                'maps' => 'Maps', 'google_tv' => 'Google TV', 'google_owned_channels' => 'Google channels',
            ][strtolower($segment)] ?? ucfirst(str_replace('_', ' ', strtolower($segment))),
            'publisher_platform', 'platform_position', 'device_platform' => ucfirst(str_replace('_', ' ', $segment)),
            default => $segment,
        };
    }

    protected static function countryName(string $code): string
    {
        if ($code === '' || strtolower($code) === 'unknown') {
            return 'Unknown';
        }
        if (strlen($code) === 2 && class_exists(\Locale::class)) {
            $name = \Locale::getDisplayRegion('-' . strtoupper($code), 'en');
            if ($name && strcasecmp($name, $code) !== 0) {
                return $name;
            }
        }

        return $code;
    }
}
