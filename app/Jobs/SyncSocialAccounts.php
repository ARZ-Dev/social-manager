<?php

namespace App\Jobs;

use App\Models\Social\SocialAccount;
use App\Services\Meta\MetaSyncService;
use App\Services\Meta\SyncStopped;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Carbon;

/**
 * Fetches Meta data for a set of accounts, one after another, from $since until today.
 *
 * Dispatched with dispatchAfterResponse(), so it runs right after the page gets
 * its answer (no queue worker needed) and a long fetch can't hit the web
 * server's timeout.
 *
 * Each account of the run carries the run's id (sync_run_id) and a state
 * (sync_state): queued → running → done / failed / stopped. The page lists
 * the accounts of the run with their state. Pressing Stop marks the waiting and
 * running accounts "stopped"; a newer fetch replaces the run id. The job checks
 * between steps, stops that account and skips the ones not yet started.
 * sync_queued_at is a heartbeat while waiting/running and is cleared at the end.
 */
class SyncSocialAccounts
{
    use Dispatchable;

    public const ACTIVE = ['queued', 'running'];

    /** @param int[] $accountIds */
    public function __construct(
        public array $accountIds,
        public string $runId,
        public string $since, // Y-m-d
    ) {
    }

    public function handle(MetaSyncService $sync): void
    {
        @set_time_limit(0);
        ignore_user_abort(true);

        $since = Carbon::parse($this->since)->startOfDay();
        $lastBeat = 0;

        foreach ($this->accountIds as $id) {
            $ours = fn () => SocialAccount::whereKey($id)->where('sync_run_id', $this->runId)->whereIn('sync_state', self::ACTIVE);
            $stillOurs = function () use ($ours, &$lastBeat) {
                // Heartbeat about once a minute, so a long fetch isn't mistaken for a dead one.
                if (time() - $lastBeat >= 60) {
                    SocialAccount::where('sync_run_id', $this->runId)->whereIn('sync_state', self::ACTIVE)
                        ->update(['sync_queued_at' => now()]);
                    $lastBeat = time();
                }

                return $ours()->exists();
            };

            if (!$stillOurs()) {
                continue; // stopped, or a newer fetch took it over
            }

            $account = SocialAccount::with(['company', 'parent'])->find($id);
            if (!$account) {
                continue;
            }

            $ours()->update(['sync_state' => 'running', 'sync_progress' => 0, 'sync_step' => null]);
            $outcome = 'done';

            // Saved for the page's progress list (the service throttles the calls).
            $onProgress = fn (int $percent, string $step) => $ours()->update(['sync_progress' => $percent, 'sync_step' => $step]);

            try {
                $result = $sync->syncAccount($account, $since, fn () => !$stillOurs(), $onProgress);
                // What this fetch brought in, for the progress list.
                $fresh = $account->fresh();
                $fresh->update(['meta' => array_merge($fresh->meta ?? [], ['last_fetch_counts' => array_filter($result['counts'])])]);
            } catch (SyncStopped) {
                $outcome = 'stopped';
                $account->update(['last_sync_error' => 'The last fetch was stopped before it finished, so some data may be missing.']);
            } catch (\Throwable $e) {
                $outcome = 'failed';
                report($e);
                $account->update(['last_sync_error' => 'The fetch stopped: ' . $e->getMessage()]);
            } finally {
                // Only if the run still owns the account (a newer fetch may have taken over).
                SocialAccount::whereKey($id)->where('sync_run_id', $this->runId)
                    ->update(['sync_queued_at' => null, 'sync_state' => $outcome, 'sync_step' => null]
                        + ($outcome === 'done' ? ['sync_progress' => 100] : []));
            }
        }
    }
}
