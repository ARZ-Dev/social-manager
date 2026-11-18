@php
    $vs = 'vs previous ' . $days . ' days';
    $cards = [
        [
            'label' => 'Followers', 'icon' => 'users-group', 'value' => $kpis['followers']['value'],
            'delta' => null,
            'note' => $kpis['followers']['gain'] !== null
                ? ($kpis['followers']['gain'] >= 0 ? '+' : '') . number_format($kpis['followers']['gain']) . ' in the last ' . $days . ' days'
                : 'Across ' . $compare->count() . ' ' . str('account')->plural($compare->count()),
        ],
        [
            'label' => 'Reach', 'icon' => 'eye', 'value' => $kpis['reach']['value'],
            'delta' => $kpis['reach']['change'],
            'note' => $kpis['reach']['value'] === null ? 'No reach data yet' : ($kpis['reach']['change'] !== null ? $vs : 'Sum of daily reach'),
        ],
        [
            'label' => 'Engagement', 'icon' => 'heart', 'value' => $kpis['engagement']['value'],
            'delta' => $kpis['engagement']['change'],
            'note' => $kpis['engagement']['perPost'] !== null ? number_format($kpis['engagement']['perPost']) . ' per post' : 'Likes, comments and shares',
        ],
        [
            'label' => 'Posts published', 'icon' => 'photo', 'value' => $kpis['posts']['value'],
            'delta' => $kpis['posts']['change'],
            'note' => $kpis['posts']['change'] !== null ? $vs : 'In the last ' . $days . ' days',
        ],
    ];

    // Same colours as the chart (resources/js/app.js → seriesColors).
    $used = [];
    $swatches = collect($trend['series'])->map(function ($s) use (&$used, $platforms) {
        $list = $platforms[$s['platform']]['colors'] ?? ['#009DFF'];
        $used[$s['platform']] = ($used[$s['platform']] ?? -1) + 1;
        return $list[$used[$s['platform']] % count($list)];
    });
@endphp

{{-- KPIs --}}
<div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    @foreach($cards as $card)
        <div class="card p-5">
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ $card['label'] }}</p>
                <x-icon :name="$card['icon']" class="size-5 text-slate-300 dark:text-slate-600" />
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <p class="text-3xl font-semibold tracking-tight text-slate-900 dark:text-white" @if($card['value'] !== null) title="{{ number_format($card['value']) }}" @endif>
                    {{ $card['value'] === null ? '—' : $fmt($card['value']) }}
                </p>
                <x-ui.delta :value="$card['delta']" />
            </div>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $card['note'] }}</p>
        </div>
    @endforeach
</div>

<div class="grid gap-6 xl:grid-cols-3">
    {{-- Daily trend --}}
    <div class="card xl:col-span-2">
        <div class="card-header">
            <div>
                <h2 class="card-title">{{ $trend['label'] ?? 'Daily trend' }}</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    Last {{ $days }} days{{ $trend['agg'] === 'last' ? '' : ', per day' }}{{ $selectedAccount ? ' · ' . $selectedAccount->display_name : '' }}
                </p>
            </div>
            @if($trendGroups->count() > 1)
                <div class="segmented flex-wrap" role="group" aria-label="Metric">
                    @foreach($trendGroups as $key => $label)
                        <button type="button" wire:click="setTrendMetric('{{ $key }}')" aria-pressed="{{ $trendKey === $key ? 'true' : 'false' }}">{{ $label }}</button>
                    @endforeach
                </div>
            @endif
        </div>
        <div class="card-body">
            @if(empty($trend['series']))
                <x-ui.empty icon="chart-line" title="No daily numbers for this period" compact>
                    Daily insights appear after the next fetch. New Pages and accounts can take a day or two to report.
                </x-ui.empty>
            @else
                <div wire:ignore wire:key="trend-{{ md5(json_encode($trend)) }}" x-data="trendChart(@js($trend))">
                    <div x-ref="canvas" class="h-[300px]" role="img" aria-label="{{ $trend['label'] }} per day over the last {{ $days }} days"></div>
                </div>
                <ul class="mt-4 flex flex-wrap gap-x-6 gap-y-2 border-t border-slate-100 pt-4 dark:border-slate-800">
                    @foreach($trend['series'] as $i => $s)
                        <li class="flex items-center gap-2 text-sm">
                            <svg class="h-2 w-6 shrink-0" viewBox="0 0 24 8" aria-hidden="true">
                                <line x1="0" y1="4" x2="24" y2="4" stroke="{{ $swatches[$i] }}" stroke-width="2.5" stroke-dasharray="{{ [0, 6, 2][$i % 3] ?: 'none' }}" />
                            </svg>
                            <span class="text-slate-600 dark:text-slate-300">{{ $s['name'] }}</span>
                            <span class="font-semibold tabular-nums text-slate-900 dark:text-white">{{ $s['summary'] === null ? '—' : number_format($s['summary']) }}</span>
                            <span class="text-xs text-slate-400">{{ $trend['agg'] === 'last' ? 'latest' : 'total' }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    {{-- Top posts --}}
    <div class="card flex flex-col">
        <div class="card-header">
            <div>
                <h2 class="card-title">Top posts</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">Most engagement, last {{ $days }} days</p>
            </div>
            <button type="button" wire:click="setTab('posts')" class="link text-xs">All posts</button>
        </div>
        @forelse($topPosts as $i => $post)
            <button type="button" wire:click="openPostDetail({{ $post->id }})" wire:key="top-{{ $post->id }}"
                class="flex w-full items-center gap-3 border-b border-slate-100 px-5 py-3 text-left transition-colors last:border-0 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/40">
                <span class="w-4 text-center text-xs font-semibold text-slate-400">{{ $i + 1 }}</span>
                <span class="relative size-12 shrink-0 overflow-hidden rounded-lg bg-slate-100 dark:bg-slate-800">
                    <x-icon name="photo" class="absolute inset-0 m-auto size-4 text-slate-300 dark:text-slate-600" />
                    @if($post->thumbnail_url || $post->media_url)
                        <img src="{{ $post->thumbnail_url ?: $post->media_url }}" alt="" loading="lazy" class="relative size-full object-cover" onerror="this.remove()">
                    @endif
                </span>
                <span class="min-w-0 flex-1">
                    <span class="line-clamp-1 text-sm text-slate-900 dark:text-white">{{ $post->message ?: '(no caption)' }}</span>
                    <span class="mt-0.5 flex items-center gap-1 text-xs text-slate-500 dark:text-slate-400">
                        <x-icon :name="$platforms[$post->account->platform]['icon']" class="size-3.5" /> {{ $post->posted_at?->format('j M') }}
                    </span>
                </span>
                <span class="text-right">
                    <span class="block text-sm font-semibold tabular-nums text-slate-900 dark:text-white">{{ $fmt($post->engagement) }}</span>
                    <span class="block text-[11px] text-slate-400">eng.</span>
                </span>
            </button>
        @empty
            <x-ui.empty icon="photo" title="No posts in this period" compact class="flex-1" />
        @endforelse
    </div>
</div>

{{-- Accounts side by side --}}
@if($compare->count() > 1)
    <div class="card mt-6 overflow-hidden">
        <div class="card-header">
            <div>
                <h2 class="card-title">Accounts compared</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">Last {{ $days }} days</p>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Account</th>
                        <th class="!text-right">Followers</th>
                        <th class="!text-right">Posts</th>
                        <th class="!text-right">Engagement</th>
                        <th class="!text-right">Per post</th>
                        <th class="!text-right">Reach</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($compare as $row)
                        <tr wire:key="cmp-{{ $row['account']->id }}">
                            <td>
                                <button type="button" wire:click="setAccount({{ $row['account']->id }})" class="flex items-center gap-3 text-left hover:underline" title="Show only this account">
                                    @include('livewire.social.partials.avatar', ['acc' => $row['account'], 'size' => 'sm'])
                                    <span class="font-medium text-slate-900 dark:text-white">{{ $row['account']->display_name }}</span>
                                </button>
                            </td>
                            <td class="text-right tabular-nums">{{ $row['followers'] !== null ? number_format($row['followers']) : '—' }}</td>
                            <td class="text-right tabular-nums">{{ number_format($row['posts']) }}</td>
                            <td class="text-right tabular-nums">{{ number_format($row['engagement']) }}</td>
                            <td class="text-right tabular-nums">{{ $row['perPost'] !== null ? number_format($row['perPost']) : '—' }}</td>
                            <td class="text-right tabular-nums">{{ $row['reach'] !== null ? number_format($row['reach']) : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
