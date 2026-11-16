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

    public function comments(): HasMany
    {
        return $this->hasMany(SocialComment::class);
    }
}
