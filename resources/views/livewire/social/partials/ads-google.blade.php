@php
    $money = fn ($v) => ($currency ? $currency . ' ' : '') . number_format((float) $v, 2);
    $dec = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.') ?: '0';
    $pct = fn ($part, $whole) => $whole ? number_format($part / $whole * 100, 2) . '%' : '—';

    $spend = (float) $gTotals->spend;
    $impr = (int) $gTotals->impressions;
    $clicks = (int) $gTotals->clicks;
    $conv = (float) $gTotals->conversions;
    $value = (float) $gTotals->conversion_value;
    $inter = (int) $gTotals->interactions;
    $eng = (int) $gTotals->engagements;
    $views = (int) $gTotals->video_views;

    // group => [[label, value, icon, note], ...]
    $kpiGroups = [
        'Performance' => [
            ['Cost', $money($spend), 'cash', null],
            ['Impressions', $fmt($impr), 'eye', null],
            ['Clicks', $fmt($clicks), 'click', 'CTR ' . $pct($clicks, $impr)],
            ['Avg. cost per click', $clicks ? $money($spend / $clicks) : '—', 'receipt', null],
        ],
        'Conversions' => [
            ['Conversions', $dec($conv), 'target-arrow', 'Conversion rate ' . $pct($conv, $clicks)],
            ['Cost per conversion', $conv > 0 ? $money($spend / $conv) : '—', 'coin', null],
            ['Conversion value', $money($value), 'wallet', $spend > 0 && $value > 0 ? 'Return on spend ' . number_format($value / $spend, 2) . '×' : null],
        ],
        'Engagement' => [
            ['Interactions', $fmt($inter), 'hand-click', 'Interaction rate ' . $pct($inter, $impr)],
            ['Engagements', $fmt($eng), 'heart', $eng ? 'Engagement rate ' . $pct($eng, $impr) : 'Video and rich media ads'],
            ['Video views', $fmt($views), 'player-play', null],
        ],
    ];
    $breakdownLabels = ['age' => 'Age', 'gender' => 'Gender', 'country' => 'Country', 'device' => 'Device', 'network' => 'Network'];
    $spendTotal = (float) $breakdownRows->sum('spend') ?: 1;
    $channel = fn ($type) => $type ? \Illuminate\Support\Str::headline(strtolower($type)) : null;
    $period = $adsSince ? 'From ' . \Illuminate\Support\Carbon::parse($adsSince)->format('j M Y') . ' to the last fetch' : 'Period of the last fetch';
    $gColors = $platforms['google_ads']['colors'];
@endphp

@if($mixedCurrency)
    <div class="alert alert-warning mb-4">
        <x-icon name="alert-triangle" class="size-5 shrink-0" />
        <p>These Google Ads accounts use different currencies, so totals mix them. Pick one account above for exact figures.</p>
    </div>
@endif

{{-- KPIs: performance on one row, then conversions and engagement side by side on wide screens --}}
<p class="mb-3 text-xs text-slate-500 dark:text-slate-400">{{ $period }}{{ $selectedAccount ? ' · ' . $selectedAccount->display_name : '' }}</p>
<div class="mb-6 grid gap-x-6 gap-y-5 2xl:grid-cols-2">
    @foreach($kpiGroups as $group => $cards)
        <section @class(['2xl:col-span-2' => $loop->first]) aria-label="{{ $group }}">
            <h3 class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">{{ $group }}</h3>
            <div @class(['grid gap-4', 'grid-cols-2 lg:grid-cols-4' => count($cards) === 4, 'grid-cols-1 sm:grid-cols-3' => count($cards) === 3])>
                @foreach($cards as [$label, $value, $icon, $note])
                    <div class="card p-4">
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ $label }}</p>
                            <x-icon :name="$icon" class="size-4 shrink-0 text-slate-300 dark:text-slate-600" />
                        </div>
                        <p class="mt-1.5 break-words text-xl font-semibold tracking-tight text-slate-900 dark:text-white">{{ $value }}</p>
                        @if($note)
                            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $note }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    @endforeach
</div>

{{-- Daily results --}}
<div class="card mb-6">
    <div class="card-header">
        <div>
            <h2 class="card-title">{{ $gTrend['label'] }} per day</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">Whole account, {{ lcfirst($period) }}</p>
        </div>
        <div class="segmented flex-wrap" role="group" aria-label="Metric">
            @foreach(\App\Livewire\Social\SocialMediaView::GOOGLE_TREND_METRICS as $key => $label)
                <button type="button" wire:click="setGoogleTrend('{{ $key }}')" aria-pressed="{{ $googleTrend === $key ? 'true' : 'false' }}">{{ $label }}</button>
            @endforeach
        </div>
    </div>
    <div class="card-body">
        @if(empty($gTrend['series']))
            <x-ui.empty icon="chart-line" title="No daily results yet" compact>They appear after the next fetch.</x-ui.empty>
        @else
            <div wire:ignore wire:key="gtrend-{{ md5(json_encode($gTrend)) }}" x-data="trendChart(@js($gTrend))">
                <div x-ref="canvas" class="h-[300px]" role="img" aria-label="{{ $gTrend['label'] }} per day"></div>
            </div>
            <ul class="mt-4 flex flex-wrap gap-x-6 gap-y-2 border-t border-slate-100 pt-4 dark:border-slate-800">
                @foreach($gTrend['series'] as $i => $s)
                    <li class="flex items-center gap-2 text-sm">
                        <svg class="h-2 w-6 shrink-0" viewBox="0 0 24 8" aria-hidden="true">
                            <line x1="0" y1="4" x2="24" y2="4" stroke="{{ $gColors[$i % count($gColors)] }}" stroke-width="2.5" stroke-dasharray="{{ [0, 6, 2][$i % 3] ?: 'none' }}" />
                        </svg>
                        <span class="text-slate-600 dark:text-slate-300">{{ $s['name'] }}</span>
                        <span class="font-semibold tabular-nums text-slate-900 dark:text-white">
                            {{ $googleTrend === 'spend' ? $money($s['summary']) : ($googleTrend === 'conversions' ? $dec($s['summary']) : number_format($s['summary'])) }}
                        </span>
                        <span class="text-xs text-slate-400">total</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>

{{-- Audience and placement --}}
<div class="card mb-6 overflow-hidden">
    <div class="card-header">
        <div class="min-w-0">
            <h2 class="card-title">Results by audience</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">
                @if($breakdownAdModel)
                    Campaign: <span class="font-medium text-slate-700 dark:text-slate-300">{{ $breakdownAdModel->name }}</span> ·
                    <button type="button" class="link" wire:click="toggleAdBreakdown({{ $breakdownAdModel->id }})">show all campaigns</button>
                @else
                    All campaigns, {{ lcfirst($period) }}. Use <x-icon name="chart-pie" class="inline size-3.5 align-text-bottom" /> on a campaign to see it alone.
                @endif
            </p>
        </div>
        <div class="segmented flex-wrap" role="group" aria-label="Breakdown">
            @foreach(array_keys(config('google_ads.breakdowns')) as $name)
                <button type="button" wire:click="setBreakdown('{{ $name }}')" aria-pressed="{{ $breakdown === $name ? 'true' : 'false' }}">
                    {{ $breakdownLabels[$name] ?? \Illuminate\Support\Str::headline($name) }}
                </button>
            @endforeach
        </div>
    </div>
    @if($breakdownRows->isEmpty())
        <x-ui.empty icon="chart-pie" title="No results for this split yet" compact>
            @if(in_array($breakdown, ['age', 'gender']))
                Age and gender come from Search, Display and Video campaigns. Performance Max campaigns don't report them.
            @else
                It's filled in by the next fetch.
            @endif
        </x-ui.empty>
    @else
        <div class="relative overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Segment</th>
                        <th class="w-1/4">Share of cost</th>
                        <th class="!text-right">Cost</th>
                        <th class="!text-right">Impressions</th>
                        <th class="!text-right">Clicks</th>
                        <th class="!text-right">CTR</th>
                        <th class="!text-right">Conversions</th>
                        <th class="!text-right">Conv. rate</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($breakdownRows as $row)
                        @php $share = (float) $row->spend / $spendTotal * 100; @endphp
                        <tr>
                            <td class="whitespace-nowrap font-medium text-slate-900 dark:text-white">{{ \App\Models\Social\SocialAdBreakdown::label($breakdown, $row->segment) }}</td>
                            <td>
                                <div class="flex items-center gap-2">
                                    <div class="meter"><span class="!bg-[#34A853]" style="width: {{ round($share, 1) }}%"></span></div>
                                    <span class="w-10 shrink-0 text-right text-xs tabular-nums text-slate-500 dark:text-slate-400">{{ round($share) }}%</span>
                                </div>
                            </td>
                            <td class="text-right tabular-nums">{{ number_format((float) $row->spend, 2) }}</td>
                            <td class="text-right tabular-nums">{{ number_format((int) $row->impressions) }}</td>
                            <td class="text-right tabular-nums">{{ number_format((int) $row->clicks) }}</td>
                            <td class="text-right tabular-nums">{{ $pct((int) $row->clicks, (int) $row->impressions) }}</td>
                            <td class="text-right tabular-nums">{{ $dec($row->conversions) }}</td>
                            <td class="text-right tabular-nums">{{ $pct((float) $row->conversions, (int) $row->clicks) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if(in_array($breakdown, ['age', 'gender']))
            <p class="border-t border-slate-100 px-5 py-2.5 text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400">
                Covers Search, Display and Video campaigns. Performance Max campaigns don't report age or gender.
            </p>
        @endif
    @endif
</div>

{{-- Campaigns --}}
<div class="card overflow-hidden">
    <div class="card-header">
        <div>
            <h2 class="card-title">Campaigns</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">{{ (int) $gTotals->total }} {{ str('campaign')->plural((int) $gTotals->total) }}, {{ (int) $gTotals->active }} enabled</p>
        </div>
        <div class="flex items-center gap-2">
            <label for="gads-sort" class="text-xs text-slate-500 dark:text-slate-400">Sort by</label>
            <select id="gads-sort" wire:model.live="adSort" class="select w-auto py-1.5">
                <option value="spend">Highest cost</option>
                <option value="conversions">Most conversions</option>
                <option value="clicks">Most clicks</option>
                <option value="newest">Newest</option>
            </select>
        </div>
    </div>
    @if($gCampaigns->isEmpty())
        <x-ui.empty icon="brand-google" title="No campaigns yet">Campaigns of the linked Google Ads accounts appear after a fetch.</x-ui.empty>
    @else
        <div class="relative overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Campaign</th>
                        <th>Status</th>
                        <th class="!text-right">Cost</th>
                        <th class="!text-right">Impressions</th>
                        <th class="!text-right">Clicks</th>
                        <th class="!text-right">CTR</th>
                        <th class="!text-right">Conversions</th>
                        <th class="!text-right">Cost / conv.</th>
                        <th class="!text-right">Conv. value</th>
                        <th><span class="sr-only">Breakdown</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($gCampaigns as $c)
                        @php
                            $on = $breakdownAd === $c->id;
                            [$statusLabel, $statusClass] = match (true) {
                                $c->status === 'REMOVED' => ['Removed', 'badge-gray'],
                                $c->status === 'PAUSED' => ['Paused', 'badge-gray'],
                                $c->effective_status === 'SERVING' => ['Active', 'badge-green'],
                                $c->effective_status === 'PENDING' => ['Scheduled', 'badge-yellow'],
                                $c->effective_status === 'ENDED' => ['Ended', 'badge-gray'],
                                $c->effective_status === 'SUSPENDED' => ['Suspended', 'badge-red'],
                                default => [ucfirst(strtolower($c->status ?? 'unknown')), 'badge-gray'],
                            };
                        @endphp
                        <tr wire:key="gc-{{ $c->id }}" @class(['bg-brand-50/60 dark:bg-brand-500/5' => $on])>
                            <td class="max-w-xs">
                                <p class="truncate font-medium text-slate-900 dark:text-white">{{ $c->name }}</p>
                                <p class="truncate text-xs text-slate-500 dark:text-slate-400">
                                    {{ collect([$channel($c->objective), $c->started_at ? 'started ' . $c->started_at->format('j M Y') : null, $accounts->where('platform', 'google_ads')->count() > 1 && !$selectedAccount ? $c->account->display_name : null])->filter()->join(' · ') }}
                                </p>
                            </td>
                            <td><span class="badge {{ $statusClass }}">{{ $statusLabel }}</span></td>
                            <td class="whitespace-nowrap text-right tabular-nums">{{ number_format((float) $c->spend, 2) }} <span class="text-xs text-slate-400">{{ $c->account->meta['currency'] ?? '' }}</span></td>
                            <td class="text-right tabular-nums">{{ number_format($c->impressions) }}</td>
                            <td class="text-right tabular-nums">{{ number_format($c->clicks) }}</td>
                            <td class="text-right tabular-nums">{{ $pct($c->clicks, $c->impressions) }}</td>
                            <td class="text-right tabular-nums">{{ $dec($c->conversions) }}</td>
                            <td class="text-right tabular-nums">{{ (float) $c->conversions > 0 ? number_format((float) $c->spend / (float) $c->conversions, 2) : '—' }}</td>
                            <td class="text-right tabular-nums">{{ number_format((float) $c->conversion_value, 2) }}</td>
                            <td class="text-right">
                                <button type="button" wire:click="toggleAdBreakdown({{ $c->id }})" aria-pressed="{{ $on ? 'true' : 'false' }}"
                                    @class(['btn btn-icon btn-sm', 'btn-primary' => $on, 'btn-ghost' => !$on]) title="Audience for this campaign" aria-label="Audience for {{ $c->name }}">
                                    <x-icon name="chart-pie" class="size-4" />
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($gCampaigns->hasPages())
            <div class="border-t border-slate-200 p-4 dark:border-slate-800">{{ $gCampaigns->links() }}</div>
        @endif
    @endif
</div>
