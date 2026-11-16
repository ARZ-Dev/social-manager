<?php

namespace App\Models\Social;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SocialConversation extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class, 'social_account_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SocialMessage::class);
    }
}
