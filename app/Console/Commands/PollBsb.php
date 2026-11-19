<?php

namespace App\Console\Commands;

use App\Livewire\Social\SocialMediaView;
use App\Models\Social\SocialAccount;
use App\Services\Bsb\BsbSyncService;
use Illuminate\Console\Command;

/**
 * Brings every BSB account's message log up to date. BSB's API only lists
 * today's messages, so this must run regularly (routes/console.php schedules
 * it every few minutes) or messages are missed once their day is over.
 */
class PollBsb extends Command
{
    protected $signature = 'bsb:poll {--account= : Only this social_accounts.id}';

    protected $description = 'Check BSB accounts for new messages and delivery updates';

    public function handle(BsbSyncService $sync): int
    {
        $accounts = SocialAccount::with('company')
            ->where('platform', SocialAccount::BSB)
            ->when($this->option('account'), fn ($q, $id) => $q->whereKey($id))
            ->get();

        foreach ($accounts as $account) {
            if (!$account->company) {
                continue; // company deleted
            }
            // Leave accounts a manual fetch is working on alone.
            if ($account->sync_queued_at && $account->sync_queued_at->gt(now()->subMinutes(SocialMediaView::SYNC_STALE_MINUTES))) {
                $this->line("{$account->display_name}: skipped, a fetch is running");
                continue;
            }

            try {
                $result = $sync->syncAccount($account);
                $c = $result['counts'];
                $this->line(sprintf('%s: %d today, %d new, %d status updates%s', $account->display_name,
                    $c['messages'] ?? 0, $c['new messages'] ?? 0, $c['status updates'] ?? 0,
                    $result['warnings'] ? ' (warnings: ' . implode(' | ', $result['warnings']) . ')' : ''));
            } catch (\Throwable $e) {
                report($e);
                $account->update(['last_sync_error' => 'The automatic check stopped: ' . $e->getMessage()]);
                $this->error("{$account->display_name}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
