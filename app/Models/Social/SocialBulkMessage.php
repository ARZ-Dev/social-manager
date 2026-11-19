<?php

namespace App\Models\Social;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A message sent through a BSB account (SMS or WhatsApp) and its delivery status.
 * BSB reports no text, sender or send time: first_seen_at is when a check first
 * saw the message.
 */
class SocialBulkMessage extends Model
{
    public const ROUTES = ['sms' => 'SMS', 'wp' => 'WhatsApp'];

    /** Reached the phone. READ (WhatsApp only) means it was also opened. */
    public const DELIVERED = ['DELIVERED', 'READ'];

    /** Will never arrive. BSB documents FAILED; the others are common SMS delivery reports. */
    public const FAILED = ['FAILED', 'UNDELIVERED', 'REJECTED', 'EXPIRED'];

    /** Everything else (SENT, PENDING, unknown) is still on the way. */

    /**
     * Statuses that won't change any more on a route. A delivered WhatsApp
     * message can still be read, so it keeps being re-checked; SMS can't.
     */
    public static function finalStatuses(string $route): array
    {
        return $route === 'wp'
            ? array_merge(['READ'], self::FAILED)
            : array_merge(self::DELIVERED, self::FAILED);
    }

    /** SQL CASE turning the status into its funnel group: delivered | failed | progress. */
    public static function groupSql(): string
    {
        $list = fn (array $statuses) => implode(',', array_map(fn ($s) => "'$s'", $statuses));

        return 'CASE WHEN status IN (' . $list(self::DELIVERED) . ") THEN 'delivered'"
            . ' WHEN status IN (' . $list(self::FAILED) . ") THEN 'failed' ELSE 'progress' END";
    }

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'status_changed_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class, 'social_account_id');
    }

    public function getRouteLabelAttribute(): string
    {
        return self::ROUTES[$this->route] ?? strtoupper((string) $this->route);
    }
}
