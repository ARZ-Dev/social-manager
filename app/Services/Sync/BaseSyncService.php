<?php

namespace App\Services\Sync;

use App\Models\Social\SocialAccount;
use App\Services\Meta\SyncProgress;
use App\Services\Meta\SyncStopped;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * What the Google Ads and BSB fetches share with the Meta one: a stop check
 * between steps, warnings that don't end the fetch, progress reporting, and
 * recording which period the stored data covers.
 */
abstract class BaseSyncService implements SyncsAccount
{
    /** @var string[] */
    protected array $warnings = [];

    protected CarbonInterface $since;

    protected ?\Closure $shouldStop = null;

    protected ?SyncProgress $progress = null;

    /** The API's own error class: those become warnings, anything else ends the fetch. */
    abstract protected function apiException(): string;

    protected function begin(array $steps, ?CarbonInterface $since, ?callable $shouldStop, ?callable $onProgress, int $defaultDays): void
    {
        $this->warnings = [];
        $this->shouldStop = $shouldStop ? \Closure::fromCallable($shouldStop) : null;
        $this->progress = new SyncProgress($steps, $onProgress ? \Closure::fromCallable($onProgress) : null);
        $this->since = $since ? Carbon::instance($since)->startOfDay() : now()->subDays($defaultDays)->startOfDay();
    }

    /**
     * Save when the account was fetched, its warnings, and the period the stored
     * data covers (the Overview uses it to decide whether comparisons make sense).
     */
    protected function finish(SocialAccount $account, array $counts, array $extraMeta = []): array
    {
        $meta = $account->fresh()->meta ?? [];
        $from = isset($meta['fetched_from']) ? Carbon::parse($meta['fetched_from'])->min($this->since) : $this->since;

        $account->update([
            'last_synced_at' => now(),
            'last_sync_error' => $this->warnings ? implode("\n", array_slice($this->warnings, 0, 10)) : null,
            'meta' => array_merge($meta, [
                'fetched_from' => $from->toDateString(),
                'last_fetch_since' => $this->since->toDateString(),
            ], $extraMeta),
        ]);

        return ['counts' => $counts, 'warnings' => $this->warnings];
    }

    /** Run one section; an API error becomes a warning and the fetch goes on. */
    protected function attempt(string $what, callable $fn): void
    {
        $this->checkStop();
        $class = $this->apiException();
        try {
            $fn();
        } catch (\Throwable $e) {
            if (!$e instanceof $class) {
                throw $e;
            }
            $this->warn($what, $e);
        }
    }

    protected function warn(string $what, \Throwable $e): void
    {
        $line = ucfirst($what) . ': ' . $e->getMessage();
        if (!in_array($line, $this->warnings, true)) {
            $this->warnings[] = $line;
        }
    }

    /** @throws SyncStopped */
    protected function checkStop(): void
    {
        if ($this->shouldStop && ($this->shouldStop)()) {
            throw new SyncStopped('The fetch was stopped.');
        }
    }
}
