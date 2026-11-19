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

    /** BSB's statuses, in funnel order. DELIVERED and FAILED don't change any more. */
    public const STATUSES = ['DELIVERED', 'SENT', 'PENDING', 'FAILED'];
    public const FINAL = ['DELIVERED', 'FAILED'];

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
