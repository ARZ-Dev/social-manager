<?php

namespace App\Jobs;

use App\Models\Social\SocialAccount;
use App\Services\Meta\MetaSyncService;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * Fetches Meta data for a set of accounts, one after another.
 *
 * Dispatched with dispatchAfterResponse(), so it runs right after the page gets
 * its answer (no queue worker needed) and a long fetch can't hit the web
 * server's timeout. Each account's sync_queued_at is cleared when it is done,
 * which is what the Social Media page polls for progress.
 */
class SyncSocialAccounts
{
    use Dispatchable;

    /** @param int[] $accountIds */
    public function __construct(public array $accountIds)
    {
    }

    public function handle(MetaSyncService $sync): void
    {
        @set_time_limit(0);
        ignore_user_abort(true);

        foreach ($this->accountIds as $id) {
            $account = SocialAccount::with(['company', 'parent'])->find($id);
            if (!$account) {
                continue;
            }

            try {
                $sync->syncAccount($account);
            } catch (\Throwable $e) {
                report($e);
                $account->update(['last_sync_error' => 'The fetch stopped: ' . $e->getMessage()]);
            } finally {
                SocialAccount::whereKey($id)->update(['sync_queued_at' => null]);
            }
        }
    }
}
