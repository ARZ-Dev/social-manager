<?php

namespace App\Models\Social;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialMessage extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'from_page' => 'boolean',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(SocialConversation::class, 'social_conversation_id');
    }
}
