<?php

namespace App\Services\Bsb;

use App\Models\Social\SocialAccount;
use App\Models\Social\SocialBulkMessage;
use App\Support\PhonePrefix;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Brings a BSB account's message log up to date:
 * 1. the SMS / WhatsApp balance,
 * 2. today's messages on both routes (BSB lists today's only, so new ones are
 *    added and the status of known ones updated),
 * 3. older messages still not delivered or failed, re-checked by ID.
 *
 * Run by the scheduler every few minutes (bsb:poll) and by the Fetch button.
 */
class BsbSyncService extends \App\Services\Sync\BaseSyncService
{
    protected const STEPS = ['balance' => 5, 'messages' => 60, 'statuses' => 35];

    protected BsbClient $bsb;

    protected SocialAccount $account;

    protected function apiException(): string
    {
        return BsbException::class;
    }

    /** $since is ignored: BSB only reports today's messages. */
    public function syncAccount(SocialAccount $account, ?CarbonInterface $since = null, ?callable $shouldStop = null, ?callable $onProgress = null): array
    {
        $this->begin(self::STEPS, now(), $shouldStop, $onProgress, 0);
        $this->account = $account;
        $counts = ['messages' => 0, 'new messages' => 0, 'status updates' => 0];
        $startedAt = now();

        try {
            $this->bsb = BsbClient::forAccount($account);
        } catch (BsbException $e) {
            $account->update(['last_sync_error' => $e->getMessage()]);
            return ['counts' => [], 'warnings' => [$e->getMessage()]];
        }

        $extra = [];
        $this->progress->start('balance');
        $this->attempt('balance', function () use (&$extra) {
            $balance = $this->bsb->balance();
            $extra = ['sms_balance' => $balance['sms'], 'wp_balance' => $balance['wp'], 'balance_at' => now()->toIso8601String()];
        });

        $this->progress->start('messages');
        $routes = array_keys(SocialBulkMessage::ROUTES);
        foreach ($routes as $i => $route) {
            $this->attempt(SocialBulkMessage::ROUTES[$route] . ' messages', function () use ($route, &$counts) {
                $rows = $this->bsb->todaysMessages($route, (int) config('bsb.status_limit'));
                [$total, $new, $changed] = $this->store($route, $rows);
                $counts['messages'] += $total;
                $counts['new messages'] += $new;
                $counts['status updates'] += $changed;
            });
            $this->progress->count($i + 1, count($routes));
        }

        $this->progress->start('statuses');
        $this->attempt('delivery updates', function () use (&$counts, $startedAt) {
            $counts['status updates'] += $this->recheckOpen($startedAt);
        });

        return $this->finish($account, $counts, $extra);
    }

    /**
     * Add new messages and update the status of known ones.
     *
     * @param array<int, array{ack: string, status: ?string, recipient: ?string}> $rows
     * @return array{int, int, int} [rows read, new messages, status changes]
     */
    protected function store(string $route, array $rows): array
    {
        $now = now();
        $new = 0;
        $changed = 0;

        foreach (array_chunk($rows, 500) as $chunk) {
            $this->checkStop();
            $known = SocialBulkMessage::where('social_account_id', $this->account->id)
                ->where('route', $route)
                ->whereIn('ack', array_column($chunk, 'ack'))
                ->get(['id', 'ack', 'status'])
                ->keyBy('ack');

            $inserts = [];
            $updates = [];
            foreach ($chunk as $row) {
                $existing = $known[$row['ack']] ?? null;
                if (!$existing) {
                    $inserts[$row['ack']] = [
                        'social_account_id' => $this->account->id,
                        'route' => $route,
                        'ack' => $row['ack'],
                        'recipient' => $row['recipient'],
                        'country' => PhonePrefix::country($row['recipient']),
                        'status' => $row['status'],
                        'first_seen_at' => $now,
                        'status_changed_at' => $now,
                        'delivered_at' => in_array($row['status'], SocialBulkMessage::DELIVERED, true) ? $now : null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                } elseif ($row['status'] && $row['status'] !== $existing->status) {
                    $updates[$row['status']][] = $existing->id;
                }
            }

            // insertOrIgnore: a check running at the same time may have added the same message.
            $new += SocialBulkMessage::insertOrIgnore(array_values($inserts));
            $changed += $this->applyStatuses($updates, $now);
        }

        return [count($rows), $new, $changed];
    }

    /**
     * Re-check messages of the last few days that are still sent / pending.
     * Today's are skipped: the list of today's messages just updated them.
     * BSB takes up to 500 IDs per call.
     */
    protected function recheckOpen(CarbonInterface $startedAt): int
    {
        $open = SocialBulkMessage::where('social_account_id', $this->account->id)
            ->where('first_seen_at', '>=', now()->subDays((int) config('bsb.recheck_days')))
            ->where('first_seen_at', '<', now()->startOfDay())
            ->where('updated_at', '<', $startedAt)
            ->where(fn ($q) => $q->whereNull('status')
                ->orWhere(fn ($w) => $w->where('route', 'wp')->whereNotIn('status', SocialBulkMessage::finalStatuses('wp')))
                ->orWhere(fn ($w) => $w->where('route', '!=', 'wp')->whereNotIn('status', SocialBulkMessage::finalStatuses('sms'))))
            ->get(['id', 'route', 'ack', 'status']);

        $changed = 0;
        $now = now();
        $batches = $open->filter(fn ($m) => ctype_digit($m->ack))->groupBy('route')->map->chunk(500)->flatten(1);
        foreach ($batches->values() as $i => $batch) {
            $this->checkStop();
            $route = $batch->first()->route;
            $current = $this->bsb->statusesByAcks($route, $batch->pluck('ack')->all());

            $updates = [];
            foreach ($batch as $m) {
                $status = $current[$m->ack]['status'] ?? null;
                if ($status && $status !== $m->status) {
                    $updates[$status][] = $m->id;
                }
            }
            $changed += $this->applyStatuses($updates, $now);
            $this->progress->count($i + 1, $batches->count());
        }

        return $changed;
    }

    /** @param array<string, int[]> $updates new status => message IDs */
    protected function applyStatuses(array $updates, CarbonInterface $now): int
    {
        $n = 0;
        foreach ($updates as $status => $ids) {
            $values = ['status' => $status, 'status_changed_at' => $now, 'updated_at' => $now];
            if (in_array($status, SocialBulkMessage::DELIVERED, true)) {
                // Keep the first time it was seen delivered.
                $values['delivered_at'] = DB::raw('COALESCE(`delivered_at`, ' . DB::getPdo()->quote($now->toDateTimeString()) . ')');
            }
            $n += SocialBulkMessage::whereKey($ids)->update($values);
        }

        return $n;
    }
}
