@php
    $pct = fn ($part, $whole) => $whole ? round($part / $whole * 100, 1) : null;
    $f = $smsFunnel;
    $rate = $pct($f['delivered'], $f['total']);
    $statusBadge = fn ($status) => match ($status) {
        'DELIVERED' => ['Delivered', 'badge-green'],
        'FAILED' => ['Failed', 'badge-red'],
        'SENT' => ['Sent', 'badge-blue'],
        'PENDING' => ['Pending', 'badge-yellow'],
        default => [$status ? ucfirst(strtolower($status)) : 'Unknown', 'badge-gray'],
    };
    $filters = ['' => 'All', 'delivered' => 'Delivered', 'progress' => 'On the way', 'failed' => 'Failed'];
    $logStart = $smsLogStart ? \Illuminate\Support\Carbon::parse($smsLogStart) : null;
@endphp

@if($smsChecksStale)
    <div class="alert alert-warning mb-4">
        <x-icon name="clock-exclamation" class="size-5 shrink-0" />
        <div class="flex-1">
            <p class="font-semibold">Automatic BSB checks don't seem to be running</p>
            <p class="mt-0.5 opacity-90">
                BSB only reports today's messages, so the app checks every {{ config('bsb.poll_minutes') }} minutes to keep the log complete.
                Messages from days without checks are missing. Make sure <code class="rounded bg-white/60 px-1 dark:bg-slate-900/60">php artisan schedule:run</code> runs every minute on the server.
            </p>
        </div>
    </div>
@endif

{{-- Balance per BSB account --}}
<div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    @foreach($bsbAccounts as $acc)
        <div class="card p-4" wire:key="bal-{{ $acc->id }}">
            <div class="flex items-center gap-3">
                @include('livewire.social.partials.avatar', ['acc' => $acc, 'size' => 'sm'])
                <p class="min-w-0 flex-1 truncate text-sm font-medium text-slate-900 dark:text-white">{{ $acc->display_name }}</p>
            </div>
            <dl class="mt-3 grid grid-cols-2 gap-2">
                <div>
                    <dt class="text-xs text-slate-500 dark:text-slate-400">SMS balance</dt>
                    <dd class="text-lg font-semibold tabular-nums text-slate-900 dark:text-white">{{ isset($acc->meta['sms_balance']) ? number_format((float) $acc->meta['sms_balance'], 2) : '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500 dark:text-slate-400">WhatsApp balance</dt>
                    <dd class="text-lg font-semibold tabular-nums text-slate-900 dark:text-white">{{ isset($acc->meta['wp_balance']) ? number_format((float) $acc->meta['wp_balance'], 2) : '—' }}</dd>
                </div>
            </dl>
            <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                {{ $acc->last_synced_at ? 'Checked ' . $acc->last_synced_at->diffForHumans() : 'Not checked yet' }}
            </p>
        </div>
    @endforeach
</div>

{{-- Delivery funnel --}}
@php
    $cards = [
        ['Messages sent', $f['total'], 'send', null, null],
        ['Delivered', $f['delivered'], 'checks', $pct($f['delivered'], $f['total']), 'text-green-600 dark:text-green-400'],
        ['On the way', $f['progress'], 'hourglass', $pct($f['progress'], $f['total']), 'text-slate-500 dark:text-slate-400'],
        ['Failed', $f['failed'], 'circle-x', $pct($f['failed'], $f['total']), 'text-red-600 dark:text-red-400'],
    ];
@endphp
<div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    @foreach($cards as [$label, $value, $icon, $share, $tone])
        <div class="card p-5">
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ $label }}</p>
                <x-icon :name="$icon" class="size-5 text-slate-300 dark:text-slate-600" />
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <p class="text-3xl font-semibold tracking-tight text-slate-900 dark:text-white" title="{{ number_format($value) }}">{{ $fmt($value) }}</p>
                @if($share !== null)
                    <span class="text-sm font-semibold tabular-nums {{ $tone }}">{{ $share }}%</span>
                @endif
            </div>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                @if($label === 'Messages sent')
                    Last {{ $days }} days, SMS and WhatsApp
                @elseif($label === 'Delivered')
                    Delivery rate
                @elseif($label === 'On the way')
                    Sent or pending, not confirmed yet
                @else
                    Rejected by the network or the number
                @endif
            </p>
        </div>
    @endforeach
</div>

<div class="mb-6 grid gap-6 xl:grid-cols-3">
    {{-- Per day --}}
    <div class="card xl:col-span-2">
        <div class="card-header">
            <div>
                <h2 class="card-title">Messages per day</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">By delivery status, last {{ $days }} days</p>
            </div>
        </div>
        <div class="card-body">
            @if($f['total'] === 0)
                <x-ui.empty icon="chart-line" title="No messages in this period" compact>
                    Messages sent through BSB appear here once a check has seen them.
                </x-ui.empty>
            @else
                <div wire:ignore wire:key="smstrend-{{ md5(json_encode($smsTrend)) }}" x-data="trendChart(@js($smsTrend))">
                    <div x-ref="canvas" class="h-[300px]" role="img" aria-label="Messages per day by delivery status"></div>
                </div>
                <ul class="mt-4 flex flex-wrap gap-x-6 gap-y-2 border-t border-slate-100 pt-4 dark:border-slate-800">
                    @foreach($smsTrend['series'] as $i => $s)
                        <li class="flex items-center gap-2 text-sm">
                            <svg class="h-2 w-6 shrink-0" viewBox="0 0 24 8" aria-hidden="true">
                                <line x1="0" y1="4" x2="24" y2="4" stroke="{{ $smsTrend['colors'][$i] }}" stroke-width="2.5" stroke-dasharray="{{ [0, 6, 2][$i % 3] ?: 'none' }}" />
                            </svg>
                            <span class="text-slate-600 dark:text-slate-300">{{ $s['name'] }}</span>
                            <span class="font-semibold tabular-nums text-slate-900 dark:text-white">{{ number_format($s['summary']) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    {{-- Funnel per channel, then countries --}}
    <div class="space-y-6">
        <div class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title">By channel</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Sent → delivered</p>
                </div>
            </div>
            <div class="space-y-5 p-5">
                @forelse($smsByRoute as $route => $r)
                    <div wire:key="route-{{ $route }}">
                        <div class="flex items-baseline justify-between gap-2 text-sm">
                            <span class="flex items-center gap-1.5 font-medium text-slate-900 dark:text-white">
                                <x-icon :name="$route === 'wp' ? 'brand-whatsapp' : 'device-mobile-message'" class="size-4 text-slate-400" /> {{ $r['label'] }}
                            </span>
                            <span class="tabular-nums text-slate-500 dark:text-slate-400">{{ number_format($r['delivered']) }} of {{ number_format($r['total']) }}</span>
                        </div>
                        {{-- Stacked bar: delivered / on the way / failed --}}
                        <div class="mt-2 flex h-2.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800" role="img"
                            aria-label="{{ $r['label'] }}: {{ $r['delivered'] }} delivered, {{ $r['progress'] }} on the way, {{ $r['failed'] }} failed">
                            <span class="bg-green-600" style="width: {{ $pct($r['delivered'], $r['total']) }}%"></span>
                            <span class="bg-slate-400" style="width: {{ $pct($r['progress'], $r['total']) }}%"></span>
                            <span class="bg-red-600" style="width: {{ $pct($r['failed'], $r['total']) }}%"></span>
                        </div>
                        <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">
                            <span class="font-semibold text-green-700 dark:text-green-400">{{ $pct($r['delivered'], $r['total']) }}% delivered</span>
                            · {{ $pct($r['failed'], $r['total']) }}% failed
                        </p>
                    </div>
                @empty
                    <p class="text-sm text-slate-500 dark:text-slate-400">No messages in this period.</p>
                @endforelse
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Recipients by country</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">From the number's country code</p>
                </div>
            </div>
            @if($smsCountries->isEmpty())
                <x-ui.empty icon="world" title="No recipients yet" compact />
            @else
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach($smsCountries as $row)
                        <li class="flex items-center gap-3 px-5 py-2.5 text-sm">
                            <span class="min-w-0 flex-1 truncate text-slate-900 dark:text-white">{{ $row->country ? \App\Models\Social\SocialDemographic::label('country', $row->country) : 'Unknown' }}</span>
                            <span class="tabular-nums text-slate-500 dark:text-slate-400">{{ number_format($row->n) }}</span>
                            <span class="w-14 text-right text-xs font-medium tabular-nums text-green-700 dark:text-green-400" title="Delivered">{{ $pct((int) $row->delivered, (int) $row->n) }}%</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>

{{-- All messages --}}
<div class="card overflow-hidden">
    <div class="card-header flex-wrap">
        <div>
            <h2 class="card-title">All messages</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">
                {{ number_format($smsMessages->total()) }} {{ str('message')->plural($smsMessages->total()) }}
                @if($logStart) · log kept since {{ $logStart->format('j M Y') }} @endif
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <div class="relative">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                <input type="search" wire:model.live.debounce.400ms="smsSearch" class="input w-48 py-1.5 pl-9" placeholder="Phone number" aria-label="Search by phone number" inputmode="tel">
            </div>
            <select wire:change="setSmsRoute($event.target.value)" class="select w-auto py-1.5" aria-label="Channel">
                <option value="" @selected($smsRoute === '')>All channels</option>
                @foreach(\App\Models\Social\SocialBulkMessage::ROUTES as $value => $label)
                    <option value="{{ $value }}" @selected($smsRoute === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <div class="segmented" role="group" aria-label="Status">
                @foreach($filters as $value => $label)
                    <button type="button" wire:click="setSmsStatus('{{ $value }}')" aria-pressed="{{ $smsStatus === $value ? 'true' : 'false' }}">{{ $label }}</button>
                @endforeach
            </div>
        </div>
    </div>
    @if($smsMessages->isEmpty())
        <x-ui.empty icon="message-2" :title="$smsSearch || $smsStatus || $smsRoute ? 'No messages match' : 'No messages in this period'" compact>
            @unless($smsSearch || $smsStatus || $smsRoute) Messages sent through BSB appear here once a check has seen them. @endunless
        </x-ui.empty>
    @else
        <div class="relative overflow-x-auto" wire:loading.class="opacity-50" wire:target="smsSearch">
            <table class="table">
                <thead>
                    <tr>
                        <th>Recipient</th>
                        <th>Country</th>
                        <th>Channel</th>
                        <th>Status</th>
                        <th>First seen</th>
                        <th>Last change</th>
                        <th class="!text-right">Message ID</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($smsMessages as $m)
                        @php [$label, $class] = $statusBadge($m->status); @endphp
                        <tr wire:key="bm-{{ $m->id }}">
                            <td class="whitespace-nowrap font-medium tabular-nums text-slate-900 dark:text-white">{{ $m->recipient ? '+' . ltrim($m->recipient, '+') : '—' }}</td>
                            <td class="whitespace-nowrap">{{ $m->country ? \App\Models\Social\SocialDemographic::label('country', $m->country) : '—' }}</td>
                            <td class="whitespace-nowrap">
                                <span class="inline-flex items-center gap-1.5">
                                    <x-icon :name="$m->route === 'wp' ? 'brand-whatsapp' : 'device-mobile-message'" class="size-4 text-slate-400" /> {{ $m->route_label }}
                                </span>
                            </td>
                            <td><span class="badge {{ $class }}">{{ $label }}</span></td>
                            <td class="whitespace-nowrap text-slate-500 dark:text-slate-400" title="{{ $m->first_seen_at?->format('d M Y H:i') }}">{{ $m->first_seen_at?->format('j M, H:i') }}</td>
                            <td class="whitespace-nowrap text-slate-500 dark:text-slate-400" title="{{ $m->status_changed_at?->format('d M Y H:i') }}">{{ $m->status_changed_at?->diffForHumans() }}</td>
                            <td class="text-right font-mono text-xs text-slate-500 dark:text-slate-400">{{ $m->ack }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($smsMessages->hasPages())
            <div class="border-t border-slate-200 p-4 dark:border-slate-800">{{ $smsMessages->links() }}</div>
        @endif
    @endif
    <p class="border-t border-slate-100 px-5 py-2.5 text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400">
        BSB's API reports each message's ID, recipient and delivery status only: no text, sender ID or replies.
        "First seen" is when a check first saw the message, at most {{ config('bsb.poll_minutes') }} minutes after it was sent while automatic checks run.
    </p>
</div>
