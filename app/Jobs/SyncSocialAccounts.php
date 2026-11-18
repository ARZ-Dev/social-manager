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
 * Every account carries the run's id in sync_run_id while it waits or is being
 * fetched. Pressing Stop clears it (and a newer fetch replaces it); the job
 * checks between steps, stops that account and skips the ones not yet started.
 * sync_queued_at doubles as a heartbeat while the run is alive and is cleared
 * as each account finishes, which the page polls.
 */
class SyncSocialAccounts
{
    use Dispatchable;

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
            $stillOurs = function () use ($id, &$lastBeat) {
                // Heartbeat about once a minute, so a long fetch isn't mistaken for a dead one.
                if (time() - $lastBeat >= 60) {
                    SocialAccount::where('sync_run_id', $this->runId)->update(['sync_queued_at' => now()]);
                    $lastBeat = time();
                }

                return SocialAccount::whereKey($id)->where('sync_run_id', $this->runId)->exists();
            };
            if (!$stillOurs()) {
                continue; // stopped, or a newer fetch took it over
            }

            $account = SocialAccount::with(['company', 'parent'])->find($id);
            if (!$account) {
                continue;
            }

            try {
                $sync->syncAccount($account, $since, fn () => !$stillOurs());
            } catch (SyncStopped) {
                $account->update(['last_sync_error' => 'The last fetch was stopped before it finished, so some data may be missing.']);
            } catch (\Throwable $e) {
                report($e);
                $account->update(['last_sync_error' => 'The fetch stopped: ' . $e->getMessage()]);
            } finally {
                SocialAccount::whereKey($id)->where('sync_run_id', $this->runId)
                    ->update(['sync_queued_at' => null, 'sync_run_id' => null]);
            }
        }
    }
}
