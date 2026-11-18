<?php

namespace App\Support;

/**
 * Small number formatting helpers for dashboards.
 */
class Num
{
    /** 1234 → "1.2K", 3400000 → "3.4M". */
    public static function compact(int|float|string|null $n): string
    {
        $n = (float) $n;
        $abs = abs($n);

        foreach ([1_000_000_000 => 'B', 1_000_000 => 'M', 1_000 => 'K'] as $size => $suffix) {
            if ($abs >= $size) {
                $decimals = $abs >= $size * 100 ? 0 : 1;
                $text = number_format($n / $size, $decimals);
                if ($decimals) {
                    $text = rtrim(rtrim($text, '0'), '.'); // "1.0" → "1"
                }
                return $text . $suffix;
            }
        }

        return number_format($n, $n == (int) $n ? 0 : 2);
    }

    /** Percentage change from $previous to $current, or null when there is nothing to compare. */
    public static function change(int|float|null $current, int|float|null $previous): ?float
    {
        if (!$previous || $current === null) {
            return null;
        }

        return round(($current - $previous) / $previous * 100, 1);
    }
}
