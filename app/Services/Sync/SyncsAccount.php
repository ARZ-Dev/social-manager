<?php

namespace App\Services\Sync;

use App\Models\Social\SocialAccount;
use Carbon\CarbonInterface;

/**
 * Fetches one linked account's data (see SyncSocialAccounts).
 */
interface SyncsAccount
{
    /**
     * Fetch from $since until today. $shouldStop is checked between steps; when
     * it returns true a SyncStopped exception ends the fetch, keeping what was
     * saved. $onProgress(int $percent, string $step) is called as it moves on.
     *
     * @return array{counts: array<string,int>, warnings: string[]}
     */
    public function syncAccount(SocialAccount $account, ?CarbonInterface $since = null, ?callable $shouldStop = null, ?callable $onProgress = null): array;
}
