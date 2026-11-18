@php
    $money = fn ($v) => ($currency ? $currency . ' ' : '') . number_format((float) $v, 2);
    $spend = (float) $adTotals->spend;
    $impr = (int) $adTotals->impressions;
    $clicks = (int) $adTotals->clicks;
    $kpis = [
        ['Spend', $money($spend), 'cash'],
        ['Impressions', $fmt($impr), 'eye'],
        ['Reach', $fmt((int) $adTotals->reach), 'users'],
        ['Clicks', $fmt($clicks), 'click'],
        ['CTR', $impr ? number_format($clicks / $impr * 100, 2) . '%' : '—', 'percentage'],
        ['Cost per click', $clicks ? $money($spend / $clicks) : '—', 'receipt'],
    ];
    $breakdownLabels = ['age_gender' => 'Age & gender', 'country' => 'Country', 'placement' => 'Placement', 'device' => 'Device'];
    $spendTotal = (float) $breakdownRows->sum('spend') ?: 1;
@endphp

@if($mixedCurrency)
    <div class="alert alert-warning mb-4">
        <x-icon name="alert-triangle" class="size-5 shrink-0" />
        <p>These ad accounts use different currencies, so totals mix them. Pick one ad account above for exact figures.</p>
    </div>
@endif

{{-- KPIs --}}
<div class="mb-6 grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-6">
    @foreach($kpis as [$label, $value, $icon])
        <div class="card p-4">
            <div class="flex items-center justify-between gap-2">
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ $label }}</p>
                <x-icon :name="$icon" class="size-4 text-slate-300 dark:text-slate-600" />
            </div>
            <p class="mt-1.5 truncate text-xl font-semibold tracking-tight text-slate-900 dark:text-white">{{ $value }}</p>
        </div>
    @endforeach
</div>

{{-- Breakdown --}}
<div class="card mb-6 overflow-hidden">
    <div class="card-header">
        <div class="min-w-0">
            <h2 class="card-title">Results by audience and placement</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">
                @if($breakdownAdModel)
                    Ad: <span class="font-medium text-slate-700 dark:text-slate-300">{{ $breakdownAdModel->name }}</span> ·
                    <button type="button" class="link" wire:click="toggleAdBreakdown({{ $breakdownAdModel->id }})">show all ads</button>
                @else
                    All ads, last {{ config('meta.history_days') }} days. Use <x-icon name="chart-pie" class="inline size-3.5 align-text-bottom" /> on an ad to see it alone.
                @endif
            </p>
        </div>
        <div class="segmented" role="group" aria-label="Breakdown">
            @foreach(array_keys(config('meta.ad_breakdowns')) as $name)
                <button type="button" wire:click="setBreakdown('{{ $name }}')" aria-pressed="{{ $breakdown === $name ? 'true' : 'false' }}">
                    {{ $breakdownLabels[$name] ?? \Illuminate\Support\Str::headline($name) }}
                </button>
            @endforeach
        </div>
    </div>
    @if($breakdownRows->isEmpty())
        <x-ui.empty icon="chart-pie" title="No breakdown yet" compact>It's filled in by the next fetch.</x-ui.empty>
    @else
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Segment</th>
                        <th class="w-1/4">Share of spend</th>
                        <th class="!text-right">Spend</th>
                        <th class="!text-right">Impressions</th>
                        @if($breakdownAdModel)<th class="!text-right">Reach</th>@endif
                        <th class="!text-right">Clicks</th>
                        <th class="!text-right">CTR</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($breakdownRows as $row)
                        @php $share = (float) $row->spend / $spendTotal * 100; @endphp
                        <tr>
                            <td class="whitespace-nowrap font-medium text-slate-900 dark:text-white">{{ \App\Models\Social\SocialAdBreakdown::label($breakdown, $row->segment) }}</td>
                            <td>
                                <div class="flex items-center gap-2">
                                    <div class="meter"><span class="!bg-amber-500" style="width: {{ round($share, 1) }}%"></span></div>
                                    <span class="w-10 shrink-0 text-right text-xs tabular-nums text-slate-500 dark:text-slate-400">{{ round($share) }}%</span>
                                </div>
                            </td>
                            <td class="text-right tabular-nums">{{ number_format((float) $row->spend, 2) }}</td>
                            <td class="text-right tabular-nums">{{ number_format((int) $row->impressions) }}</td>
                            @if($breakdownAdModel)<td class="text-right tabular-nums">{{ number_format((int) $row->reach) }}</td>@endif
                            <td class="text-right tabular-nums">{{ number_format((int) $row->clicks) }}</td>
                            <td class="text-right tabular-nums">{{ $row->impressions ? number_format($row->clicks / $row->impressions * 100, 2) . '%' : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @unless($breakdownAdModel)
            <p class="border-t border-slate-100 px-5 py-2.5 text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400">
                Reach shows for one ad at a time: the same person can see several ads, so it can't be added up.
            </p>
        @endunless
    @endif
</div>

{{-- Ads --}}
<div class="card overflow-hidden">
    <div class="card-header">
        <div>
            <h2 class="card-title">Ads and boosted posts</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">{{ (int) $adTotals->total }} {{ str('ad')->plural((int) $adTotals->total) }}, {{ (int) $adTotals->active }} active</p>
        </div>
        <div class="flex items-center gap-2">
            <label for="ad-sort" class="text-xs text-slate-500 dark:text-slate-400">Sort by</label>
            <select id="ad-sort" wire:model.live="adSort" class="select w-auto py-1.5">
                <option value="spend">Highest spend</option>
                <option value="clicks">Most clicks</option>
                <option value="newest">Newest</option>
            </select>
        </div>
    </div>
    @if($ads->isEmpty())
        <x-ui.empty icon="speakerphone" title="No ads in this period">Ads and boosts from the linked ad accounts appear after a fetch.</x-ui.empty>
    @else
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Ad</th>
                        <th>Status</th>
                        <th class="!text-right">Spend</th>
                        <th class="!text-right">Impressions</th>
                        <th class="!text-right">Reach</th>
                        <th class="!text-right">Clicks</th>
                        <th class="!text-right">CTR</th>
                        <th><span class="sr-only">Breakdown</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($ads as $ad)
                        @php $on = $breakdownAd === $ad->id; @endphp
                        <tr wire:key="ad-{{ $ad->id }}" @class(['bg-brand-50/60 dark:bg-brand-500/5' => $on])>
                            <td class="max-w-xs">
                                <p class="truncate font-medium text-slate-900 dark:text-white">{{ $ad->name }}</p>
                                <p class="truncate text-xs text-slate-500 dark:text-slate-400">
                                    {{ $ad->campaign_name }}{{ $ad->objective ? ' · ' . \Illuminate\Support\Str::of($ad->objective)->replace(['OUTCOME_', '_'], ['', ' '])->lower()->ucfirst() : '' }}
                                    · started {{ $ad->started_at?->format('j M') }}
                                </p>
                            </td>
                            <td>
                                @php $st = strtoupper($ad->effective_status ?? $ad->status ?? ''); @endphp
                                <span @class(['badge', 'badge-green' => $st === 'ACTIVE', 'badge-yellow' => in_array($st, ['PENDING_REVIEW', 'IN_PROCESS', 'WITH_ISSUES']), 'badge-red' => in_array($st, ['DISAPPROVED']), 'badge-gray' => !in_array($st, ['ACTIVE', 'PENDING_REVIEW', 'IN_PROCESS', 'WITH_ISSUES', 'DISAPPROVED'])])>
                                    {{ ucfirst(strtolower(str_replace('_', ' ', $st ?: 'unknown'))) }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap text-right tabular-nums">{{ number_format((float) $ad->spend, 2) }} <span class="text-xs text-slate-400">{{ $ad->account->meta['currency'] ?? '' }}</span></td>
                            <td class="text-right tabular-nums">{{ number_format($ad->impressions) }}</td>
                            <td class="text-right tabular-nums">{{ number_format($ad->reach) }}</td>
                            <td class="text-right tabular-nums">{{ number_format($ad->clicks) }}</td>
                            <td class="text-right tabular-nums">{{ $ad->impressions ? number_format($ad->clicks / $ad->impressions * 100, 2) . '%' : '—' }}</td>
                            <td class="text-right">
                                <button type="button" wire:click="toggleAdBreakdown({{ $ad->id }})" aria-pressed="{{ $on ? 'true' : 'false' }}"
                                    @class(['btn btn-icon btn-sm', 'btn-primary' => $on, 'btn-ghost' => !$on]) title="Breakdown for this ad" aria-label="Breakdown for {{ $ad->name }}">
                                    <x-icon name="chart-pie" class="size-4" />
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($ads->hasPages())
            <div class="border-t border-slate-200 p-4 dark:border-slate-800">{{ $ads->links() }}</div>
        @endif
    @endif
</div>
