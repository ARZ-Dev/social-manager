<?php

namespace App\Models\Social;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One ad's results for one segment of a breakdown (e.g. women 25-34).
 */
class SocialAdBreakdown extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['spend' => 'decimal:2'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class, 'social_account_id');
    }

    /**
     * Readable label for a stored segment, e.g. "25-34|female" → "25-34 · Women".
     */
    public static function label(string $breakdown, string $segment): string
    {
        $dims = explode(',', config("meta.ad_breakdowns.$breakdown", ''));
        $values = explode('|', $segment);

        return collect($values)
            ->map(fn ($value, $i) => SocialDemographic::label($dims[$i] ?? '', $value))
            ->join(' · ');
    }
}
