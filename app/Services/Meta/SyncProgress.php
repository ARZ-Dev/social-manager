<?php

namespace App\Services\Meta;

/**
 * How far one account's fetch has got, as a percentage.
 *
 * A fetch is a fixed list of steps (posts, insights, audience...), each with a
 * weight for its rough share of the time. Inside a step the service reports
 * the fraction done, e.g. how far back in the period the posts read so far go.
 * The percentage never goes backwards, and is passed to $report only when it
 * changes, at most every couple of seconds (plus on every new step).
 */
class SyncProgress
{
    /** Readable step names, shown next to the percentage. */
    public const LABELS = [
        'profile' => 'Profile',
        'posts' => 'Posts and comments',
        'insights' => 'Daily insights',
        'audience' => 'Audience',
        'messages' => 'Messages',
        'ads' => 'Ads',
        'breakdowns' => 'Ad breakdowns',
    ];

    protected float $doneWeight = 0;
    protected ?string $step = null;
    protected float $fraction = 0;
    protected ?int $lastPercent = null;
    protected float $lastReportAt = 0;

    /**
     * @param array<string, int> $weights step => weight, in fetch order
     * @param (\Closure(int $percent, string $step): void)|null $report
     */
    public function __construct(protected array $weights, protected ?\Closure $report = null)
    {
    }

    /** Move on to a step; whatever was left of the previous one counts as done. */
    public function start(string $step): void
    {
        if ($this->step !== null) {
            $this->doneWeight += $this->weights[$this->step] ?? 0;
        }
        $this->step = $step;
        $this->fraction = 0;
        $this->emit(force: true);
    }

    /** Fraction (0-1) of the current step done. */
    public function at(float $fraction): void
    {
        $this->fraction = max($this->fraction, min(1, max(0, $fraction)));
        $this->emit();
    }

    /** $done of $total parts of the current step finished. */
    public function count(int $done, int $total): void
    {
        $this->at($total > 0 ? $done / $total : 1);
    }

    public function percent(): int
    {
        $total = array_sum($this->weights);
        if ($total <= 0) {
            return 0;
        }

        $done = $this->doneWeight + ($this->weights[$this->step] ?? 0) * $this->fraction;

        // 100 only once the service says it's finished.
        return min(99, (int) floor($done / $total * 100));
    }

    protected function emit(bool $force = false): void
    {
        if (!$this->report || $this->step === null) {
            return;
        }

        $percent = $this->percent();
        $now = microtime(true);
        if (!$force && ($percent === $this->lastPercent || $now - $this->lastReportAt < 2)) {
            return;
        }

        $this->lastPercent = $percent;
        $this->lastReportAt = $now;
        ($this->report)($percent, self::LABELS[$this->step] ?? $this->step);
    }
}
