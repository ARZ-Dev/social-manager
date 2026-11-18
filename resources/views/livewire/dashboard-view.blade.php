@php
    $fmt = fn ($n) => \App\Support\Num::compact($n);
    $user = auth()->user();
    $levels = [
        'red' => 'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400',
        'yellow' => 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400',
        'blue' => 'bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-300',
        'gray' => 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400',
    ];
    $platformIcon = ['facebook' => 'brand-facebook', 'instagram' => 'brand-instagram', 'ads' => 'speakerphone'];
@endphp

<div>
    <x-ui.page-header :title="'Welcome back, ' . ($user->first_name ?: $user->username)"
        :subtitle="$lastFetched ? 'Social data last fetched ' . $lastFetched->diffForHumans() . '.' : now()->format('l, j F Y')">
        @if($canSocial)
            <x-slot:actions>
                <a href="{{ route('social') }}" wire:navigate class="btn btn-primary">
                    <x-icon name="brand-instagram" class="size-4" /> Open Social Media
                </a>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    {{-- Stats --}}
    @if($stats)
        <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach($stats as $stat)
                <div class="card p-5">
                    <div class="flex items-start justify-between gap-3">
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ $stat['label'] }}</p>
                        <span class="flex size-9 items-center justify-center rounded-lg bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-300">
                            <x-icon :name="$stat['icon']" class="size-5" />
                        </span>
                    </div>
                    <p class="mt-2 text-3xl font-semibold tracking-tight text-slate-900 dark:text-white" title="{{ number_format($stat['value']) }}">{{ $fmt($stat['value']) }}</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $stat['note'] }}</p>
                </div>
            @endforeach
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-3">
        {{-- Companies (Super Admin) or this company's accounts --}}
        <div class="xl:col-span-2">
            @if($allCompanies)
                <div class="card overflow-hidden">
                    <div class="card-header">
                        <div>
                            <h2 class="card-title">Companies</h2>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Social accounts and fetch status per company</p>
                        </div>
                        @can('company-create')
                            <a href="{{ route('companies.create') }}" wire:navigate class="btn btn-secondary btn-sm"><x-icon name="plus" class="size-4" /> Add company</a>
                        @endcan
                    </div>
                    @if($companyRows->isEmpty())
                        <x-ui.empty icon="building" title="No companies yet">Add a company to start connecting its social accounts.</x-ui.empty>
                    @else
                        <div class="overflow-x-auto">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Company</th>
                                        <th class="!text-right">Accounts</th>
                                        <th class="!text-right">Followers</th>
                                        <th>Last fetched</th>
                                        <th><span class="sr-only">Open</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($companyRows as $row)
                                        <tr wire:key="dash-co-{{ $row['company']->id }}">
                                            <td>
                                                <div class="flex items-center gap-3">
                                                    <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                                        {{ strtoupper(mb_substr($row['company']->name, 0, 2)) }}
                                                    </span>
                                                    <div class="min-w-0">
                                                        <p class="truncate font-medium text-slate-900 dark:text-white">{{ $row['company']->name }}</p>
                                                        @if(!$row['metaApp'])
                                                            <span class="badge badge-gray mt-0.5">No Meta app</span>
                                                        @elseif($row['warnings'])
                                                            <span class="badge badge-yellow mt-0.5"><x-icon name="alert-triangle" class="size-3" /> {{ $row['warnings'] }} with warnings</span>
                                                        @elseif(!$row['accounts'])
                                                            <span class="badge badge-gray mt-0.5">Not connected</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-right tabular-nums">{{ $row['accounts'] }}</td>
                                            <td class="text-right tabular-nums">{{ $row['followers'] ? $fmt($row['followers']) : '—' }}</td>
                                            <td class="whitespace-nowrap text-slate-500 dark:text-slate-400">{{ $row['lastFetched']?->diffForHumans() ?? 'Never' }}</td>
                                            <td class="text-right">
                                                @if($canSocial)
                                                    <a href="{{ route('social', ['company' => $row['company']->id]) }}" wire:navigate class="btn btn-ghost btn-icon btn-sm" aria-label="Open {{ $row['company']->name }} in Social Media">
                                                        <x-icon name="chevron-right" class="size-4" />
                                                    </a>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            @elseif($canSocial)
                <div class="card overflow-hidden">
                    <div class="card-header">
                        <div>
                            <h2 class="card-title">Linked accounts</h2>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ $user->company?->name }}</p>
                        </div>
                        <a href="{{ route('social') }}" wire:navigate class="btn btn-secondary btn-sm">Manage</a>
                    </div>
                    @forelse($accounts as $acc)
                        <div class="flex items-center gap-3 border-b border-slate-100 px-5 py-3 last:border-0 dark:border-slate-800">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                                <x-icon :name="$platformIcon[$acc->platform]" class="size-5" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-slate-900 dark:text-white">{{ $acc->display_name }}</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ $acc->last_synced_at ? 'Fetched ' . $acc->last_synced_at->diffForHumans() : 'Never fetched' }}</p>
                            </div>
                            @isset($acc->meta['followers'])
                                <span class="text-sm tabular-nums text-slate-600 dark:text-slate-300">{{ $fmt($acc->meta['followers']) }} <span class="text-xs text-slate-400">followers</span></span>
                            @endisset
                        </div>
                    @empty
                        <x-ui.empty icon="plug-connected" title="No accounts linked yet">
                            Connect your Facebook Pages and Instagram accounts to see your numbers here.
                            <x-slot:action><a href="{{ route('social') }}" wire:navigate class="btn btn-primary">Connect accounts</a></x-slot:action>
                        </x-ui.empty>
                    @endforelse
                </div>
            @endif
        </div>

        {{-- Needs attention + shortcuts --}}
        <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-1 xl:content-start">
            @if($canSocial)
                <div class="card">
                    <div class="card-header"><h2 class="card-title">Needs attention</h2></div>
                    @forelse($attention->take(6) as $item)
                        <div class="flex items-start gap-3 border-b border-slate-100 px-5 py-3 last:border-0 dark:border-slate-800">
                            <span class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg {{ $levels[$item['level']] }}">
                                <x-icon :name="$item['icon']" class="size-4" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-slate-900 dark:text-white">{{ $item['title'] }}</p>
                                <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $item['text'] }}</p>
                            </div>
                            @if($item['url'])
                                <a href="{{ $item['url'] }}" wire:navigate class="link shrink-0 text-xs">{{ $item['cta'] }}</a>
                            @endif
                        </div>
                    @empty
                        <x-ui.empty icon="circle-check" title="All good" compact>Nothing needs your attention right now.</x-ui.empty>
                    @endforelse
                    @if($attention->count() > 6)
                        <p class="border-t border-slate-100 px-5 py-2 text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400">and {{ $attention->count() - 6 }} more</p>
                    @endif
                </div>
            @endif

            <div class="card">
                <div class="card-header"><h2 class="card-title">Shortcuts</h2></div>
                <div class="grid grid-cols-2 gap-2 p-3">
                    @foreach([
                        ['social', 'Social Media', 'brand-instagram', 'social-list'],
                        ['companies', 'Companies', 'building', 'company-list'],
                        ['users.create', 'Add user', 'user-plus', 'user-create'],
                        ['roles', 'Roles', 'shield-lock', 'role-list'],
                    ] as [$route, $label, $icon, $perm])
                        @can($perm)
                            <a href="{{ route($route) }}" wire:navigate class="flex flex-col items-start gap-2 rounded-lg border border-slate-200 p-3 text-sm font-medium text-slate-700 transition-colors hover:border-brand-300 hover:bg-brand-50/50 dark:border-slate-800 dark:text-slate-200 dark:hover:border-brand-500/40 dark:hover:bg-brand-500/5">
                                <x-icon :name="$icon" class="size-5 text-brand-600 dark:text-brand-400" />
                                {{ $label }}
                            </a>
                        @endcan
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- Top posts this week --}}
    @if($canSocial && $topPosts->isNotEmpty())
        <div class="card mt-6 overflow-hidden">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Top posts this week</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">By likes, comments and shares</p>
                </div>
            </div>
            <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach($topPosts as $post)
                    <li class="flex items-center gap-4 px-5 py-3">
                        <div class="relative size-14 shrink-0 overflow-hidden rounded-lg bg-slate-100 dark:bg-slate-800">
                            <x-icon name="photo" class="absolute inset-0 m-auto size-5 text-slate-300 dark:text-slate-600" />
                            @if($post->thumbnail_url || $post->media_url)
                                <img src="{{ $post->thumbnail_url ?: $post->media_url }}" alt="" loading="lazy" class="relative size-full object-cover" onerror="this.remove()">
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="line-clamp-1 text-sm text-slate-900 dark:text-white">{{ $post->message ?: '(no caption)' }}</p>
                            <p class="mt-0.5 flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
                                <x-icon :name="$platformIcon[$post->account->platform]" class="size-3.5" />
                                {{ $post->account->display_name }} · {{ $post->posted_at?->format('D j M') }}
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-semibold tabular-nums text-slate-900 dark:text-white">{{ $fmt($post->engagement) }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">engagements</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
