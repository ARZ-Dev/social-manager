<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * How fast a Page / Instagram account answers its conversations.
 *
 * A response is the first message from the business after one or more customer
 * messages; its time runs from the first of those customer messages. Only
 * waits that started within the period count.
 */
class ResponseTimes
{
    /** Distribution buckets: label => upper bound in seconds. */
    public const BUCKETS = [
        'Under 5 min' => 300,
        '5–15 min' => 900,
        '15–60 min' => 3600,
        '1–4 hours' => 14400,
        '4–24 hours' => 86400,
        'Over a day' => PHP_INT_MAX,
    ];

    /**
     * Per conversation: every response time (seconds), the first one, whether a
     * customer wrote in the period, and since when the customer has been waiting
     * if the last word is theirs.
     *
     * @param iterable $messages objects with social_conversation_id, from_page and sent_at, in any order
     * @return array<int, array{responses: int[], first: ?int, inbound: bool, awaitingSince: ?CarbonInterface}>
     */
    public static function perConversation(iterable $messages, CarbonInterface $since): array
    {
        $byConversation = collect($messages)
            ->filter(fn ($m) => $m->sent_at !== null)
            ->groupBy('social_conversation_id');

        $out = [];
        foreach ($byConversation as $id => $list) {
            $entry = ['responses' => [], 'first' => null, 'inbound' => false, 'awaitingSince' => null];
            $waitingSince = null;

            foreach ($list->sortBy(fn ($m) => $m->sent_at->getTimestamp())->values() as $m) {
                if (!$m->from_page) {
                    $waitingSince ??= $m->sent_at;
                    $entry['inbound'] = $entry['inbound'] || $m->sent_at->gte($since);
                    continue;
                }

                if ($waitingSince !== null && $waitingSince->gte($since)) {
                    $seconds = max(0, $m->sent_at->getTimestamp() - $waitingSince->getTimestamp());
                    $entry['responses'][] = $seconds;
                    $entry['first'] ??= $seconds;
                }
                $waitingSince = null;
            }

            if ($waitingSince !== null && $waitingSince->gte($since)) {
                $entry['awaitingSince'] = $waitingSince;
            }
            $out[$id] = $entry;
        }

        return $out;
    }

    /**
     * Totals over a set of conversations (from perConversation()).
     *
     * @return array{conversations: int, replied: int, replyRate: ?float, medianFirst: ?int, median: ?int, average: ?int, within1h: ?float, awaiting: int, responses: int, buckets: array<string,int>}
     */
    public static function summary(array|Collection $conversations): array
    {
        $conversations = collect($conversations);
        $inbound = $conversations->filter(fn ($c) => $c['inbound']);
        $all = $conversations->flatMap(fn ($c) => $c['responses'])->values();
        $firsts = $conversations->pluck('first')->filter(fn ($v) => $v !== null)->values();
        $replied = $inbound->filter(fn ($c) => $c['first'] !== null)->count();

        $buckets = array_fill_keys(array_keys(self::BUCKETS), 0);
        foreach ($all as $seconds) {
            foreach (self::BUCKETS as $label => $max) {
                if ($seconds < $max) {
                    $buckets[$label]++;
                    break;
                }
            }
        }

        return [
            'conversations' => $inbound->count(),
            'replied' => $replied,
            'replyRate' => $inbound->count() ? round($replied / $inbound->count() * 100, 1) : null,
            'medianFirst' => self::median($firsts),
            'median' => self::median($all),
            'average' => $all->isEmpty() ? null : (int) round($all->avg()),
            'within1h' => $all->isEmpty() ? null : round($all->filter(fn ($s) => $s <= 3600)->count() / $all->count() * 100, 1),
            'awaiting' => $conversations->filter(fn ($c) => $c['awaitingSince'] !== null)->count(),
            'responses' => $all->count(),
            'buckets' => $buckets,
        ];
    }

    protected static function median(Collection $values): ?int
    {
        if ($values->isEmpty()) {
            return null;
        }

        $sorted = $values->sort()->values();
        $mid = intdiv($sorted->count(), 2);

        return (int) round($sorted->count() % 2 ? $sorted[$mid] : ($sorted[$mid - 1] + $sorted[$mid]) / 2);
    }

    /** 45 → "45s", 750 → "12m", 12000 → "3h 20m", 190000 → "2d 4h". */
    public static function format(?int $seconds): string
    {
        if ($seconds === null) {
            return '—';
        }
        if ($seconds < 60) {
            return $seconds . 's';
        }
        if ($seconds < 3600) {
            return intdiv($seconds, 60) . 'm';
        }
        if ($seconds < 86400) {
            $m = intdiv($seconds % 3600, 60);
            return intdiv($seconds, 3600) . 'h' . ($m ? " {$m}m" : '');
        }
        $h = intdiv($seconds % 86400, 3600);

        return intdiv($seconds, 86400) . 'd' . ($h ? " {$h}h" : '');
    }
}
