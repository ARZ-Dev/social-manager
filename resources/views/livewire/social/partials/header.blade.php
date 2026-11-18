{{-- Company, switcher, actions and the account filter --}}
<div class="card mb-6">
    <div class="flex flex-wrap items-center justify-between gap-4 p-5">
        <div class="flex min-w-0 items-center gap-4">
            <span class="hidden size-12 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-base font-semibold text-brand-700 sm:flex dark:bg-brand-500/10 dark:text-brand-300">
                {{ strtoupper(mb_substr($currentCompany->name, 0, 2)) }}
            </span>
            <div class="min-w-0">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400 dark:text-slate-500">Social Media</p>

                @if($switchCompanies->isNotEmpty())
                    <div x-data="{ open: false, q: '' }" class="relative" x-on:click.outside="open = false" x-on:keydown.escape="open = false">
                        <button type="button" class="group -ml-1 flex max-w-full items-center gap-1.5 rounded-lg px-1 py-0.5 hover:bg-slate-100 dark:hover:bg-slate-800"
                            x-on:click="open = !open; $nextTick(() => $refs.search?.focus())" :aria-expanded="open" aria-haspopup="listbox" aria-label="Switch company">
                            <span class="truncate text-xl font-semibold text-slate-900 dark:text-white">{{ $currentCompany->name }}</span>
                            <x-icon name="selector" class="size-4 shrink-0 text-slate-400 group-hover:text-slate-600 dark:group-hover:text-slate-300" />
                        </button>
                        <div x-show="open" x-transition.origin.top.left x-cloak class="dropdown-panel left-0 w-72 p-0">
                            <div class="border-b border-slate-100 p-2 dark:border-slate-800">
                                <input x-ref="search" x-model="q" type="search" class="input py-1.5" placeholder="Search companies" aria-label="Search companies">
                            </div>
                            <ul class="max-h-72 overflow-y-auto p-1" role="listbox" aria-label="Companies">
                                @foreach($switchCompanies as $c)
                                    <li x-show="!q || @js(mb_strtolower($c->name)).includes(q.toLowerCase())" wire:key="switch-{{ $c->id }}">
                                        <button type="button" class="dropdown-item justify-between" role="option" aria-selected="{{ $c->id == $currentCompany->id ? 'true' : 'false' }}"
                                            wire:click="selectCompany({{ $c->id }})" x-on:click="open = false">
                                            <span class="truncate {{ $c->id == $currentCompany->id ? 'font-semibold' : '' }}">{{ $c->name }}</span>
                                            @if($c->id == $currentCompany->id)
                                                <x-icon name="check" class="size-4 text-brand-600 dark:text-brand-400" />
                                            @else
                                                <span class="shrink-0 text-xs text-slate-400">{{ $c->social_accounts_count ?: 'No' }} {{ str('account')->plural($c->social_accounts_count) }}</span>
                                            @endif
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                            <div class="border-t border-slate-100 p-1 dark:border-slate-800">
                                <button type="button" class="dropdown-item" wire:click="clearCompany">
                                    <x-icon name="layout-grid" class="size-4" /> All companies
                                </button>
                            </div>
                        </div>
                    </div>
                @else
                    <h1 class="truncate text-xl font-semibold">{{ $currentCompany->name }}</h1>
                @endif

                <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                    {{ $accounts->count() }} {{ str('account')->plural($accounts->count()) }} linked
                    @if($lastFetched)
                        · <span title="{{ $lastFetched->format('d M Y H:i') }}">last fetched {{ $lastFetched->diffForHumans() }}</span>
                    @endif
                </p>
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            @can('social-sync')
                @if($accounts->isNotEmpty())
                    @if($syncing)
                        <button type="button" wire:click="stopFetch" wire:loading.attr="disabled" wire:target="stopFetch"
                            class="btn btn-secondary text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300">
                            <x-icon name="player-stop" class="size-4" /> Stop fetching
                        </button>
                    @else
                        <button type="button" wire:click="openFetch" wire:loading.attr="disabled" wire:target="openFetch" class="btn btn-secondary">
                            <x-icon name="refresh" class="size-4" /> Fetch data
                        </button>
                    @endif
                @endif
            @endcan
            @can('social-connect')
                @if($metaConfigured)
                    <a href="{{ route('social.connect', ['company' => $currentCompany->id]) }}" class="btn btn-primary">
                        <x-icon name="plug-connected" class="size-4" /> {{ $accounts->isEmpty() ? 'Connect accounts' : 'Connect more' }}
                    </a>
                @else
                    <button type="button" class="btn btn-primary" disabled title="Add the company's Meta App ID and App Secret first">
                        <x-icon name="plug-connected" class="size-4" /> Connect accounts
                    </button>
                @endif
            @endcan
        </div>
    </div>

    @if($accounts->isNotEmpty())
        {{-- Account strip: arrows, faded edges and mouse-wheel scrolling when it overflows --}}
        <div x-data="hscroll" class="relative border-t border-slate-200 dark:border-slate-800">
            <div x-show="canLeft" x-cloak class="pointer-events-none absolute inset-y-0 left-0 z-10 w-16 rounded-bl-xl bg-gradient-to-r from-white via-white/80 to-transparent dark:from-slate-900 dark:via-slate-900/80"></div>
            <div x-show="canRight" x-cloak class="pointer-events-none absolute inset-y-0 right-0 z-10 w-16 rounded-br-xl bg-gradient-to-l from-white via-white/80 to-transparent dark:from-slate-900 dark:via-slate-900/80"></div>
            <button type="button" x-show="canLeft" x-cloak x-on:click="by(-1)" aria-label="Scroll accounts left"
                class="absolute left-2 top-1/2 z-20 flex size-8 -translate-y-1/2 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 shadow-sm hover:bg-slate-50 hover:text-slate-900 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 dark:hover:text-white">
                <x-icon name="chevron-left" class="size-4" />
            </button>
            <button type="button" x-show="canRight" x-cloak x-on:click="by(1)" aria-label="Scroll accounts right"
                class="absolute right-2 top-1/2 z-20 flex size-8 -translate-y-1/2 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 shadow-sm hover:bg-slate-50 hover:text-slate-900 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 dark:hover:text-white">
                <x-icon name="chevron-right" class="size-4" />
            </button>

        <div x-ref="track" x-on:scroll.passive="update()" x-on:wheel="wheel($event)"
            class="flex gap-2 overflow-x-auto px-5 py-3 scrollbar-none" role="group" aria-label="Show data for">
            <button type="button" wire:click="setAccount('')" aria-pressed="{{ $account ? 'false' : 'true' }}"
                @class([
                    'flex shrink-0 items-center gap-2 rounded-full border py-1 pl-1 pr-3 text-sm font-medium transition-colors',
                    'border-brand-500 bg-brand-50 text-brand-800 dark:border-brand-400 dark:bg-brand-500/10 dark:text-brand-200' => !$account,
                    'border-slate-200 text-slate-600 hover:border-slate-300 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800' => $account,
                ])>
                <span class="flex size-8 items-center justify-center rounded-full bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400"><x-icon name="layout-grid" class="size-4" /></span>
                All accounts
            </button>

            @php
                $prevPlatform = null;
                $platformCounts = $accounts->countBy('platform');
                $groupNames = ['facebook' => 'Facebook', 'instagram' => 'Instagram', 'ads' => 'Ads'];
            @endphp
            @foreach($accounts as $acc)
                {{-- Group label before the first account of each platform --}}
                @if($acc->platform !== $prevPlatform)
                    <span wire:key="group-{{ $acc->platform }}" @class([
                        'flex shrink-0 items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500',
                        'ml-2 border-l border-slate-200 pl-4 dark:border-slate-700' => $prevPlatform !== null,
                        'ml-2' => $prevPlatform === null,
                    ])>
                        <x-icon :name="$platforms[$acc->platform]['icon']" class="size-3.5" />
                        {{ $groupNames[$acc->platform] }} · {{ $platformCounts[$acc->platform] }}
                    </span>
                    @php $prevPlatform = $acc->platform; @endphp
                @endif
                @php $active = (int) $account === $acc->id; @endphp
                <div wire:key="chip-{{ $acc->id }}" @class([
                    'flex shrink-0 items-center rounded-full border pr-1 transition-colors',
                    'border-brand-500 bg-brand-50 dark:border-brand-400 dark:bg-brand-500/10' => $active,
                    'border-slate-200 hover:border-slate-300 hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800' => !$active,
                ])>
                    <button type="button" wire:click="setAccount({{ $acc->id }})" aria-pressed="{{ $active ? 'true' : 'false' }}" class="flex items-center gap-2 rounded-full py-1 pl-1 pr-1.5 text-left">
                        @include('livewire.social.partials.avatar', ['acc' => $acc, 'size' => 'sm'])
                        <span class="leading-tight">
                            <span class="block max-w-[11rem] truncate text-sm font-medium text-slate-900 dark:text-white">{{ $acc->display_name }}</span>
                            <span class="block text-[11px] text-slate-500 dark:text-slate-400">
                                @if(isset($acc->meta['followers']))
                                    {{ $fmt($acc->meta['followers']) }} followers
                                @elseif($acc->platform === 'ads')
                                    Ads{{ isset($acc->meta['currency']) ? ' · ' . $acc->meta['currency'] : '' }}
                                @else
                                    {{ $platforms[$acc->platform]['label'] }}
                                @endif
                            </span>
                        </span>
                        @if($acc->platform === 'ads' && $acc->token_expires_at?->isPast())
                            <x-icon name="clock-exclamation" class="size-4 text-red-500" aria-label="Access expired" />
                        @elseif($acc->last_sync_error)
                            <x-icon name="alert-triangle" class="size-4 text-amber-500" aria-label="Last fetch had warnings" />
                        @endif
                    </button>
                    @can('social-disconnect')
                        <button type="button" class="flex size-7 items-center justify-center rounded-full text-slate-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10 dark:hover:text-red-400"
                            title="Disconnect" aria-label="Disconnect {{ $acc->display_name }}"
                            x-on:click="$dispatch('confirm', {
                                title: @js('Disconnect ' . $acc->display_name . '?'),
                                text: @js($acc->platform === 'facebook' ? 'Its linked Instagram account and all fetched data are removed too.' : 'All data fetched for it is removed.'),
                                confirm: 'Disconnect',
                                onConfirm: () => $wire.disconnect({{ $acc->id }})
                            })">
                            <x-icon name="unlink" class="size-3.5" />
                        </button>
                    @endcan
                </div>
            @endforeach
        </div>
        </div>
    @endif
</div>
