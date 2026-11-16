<?php

namespace App\Models\Social;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialComment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['commented_at' => 'datetime'];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(SocialPost::class, 'social_post_id');
    }
}
