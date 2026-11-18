@php $user = auth()->user(); @endphp

<header class="sticky top-0 z-30 flex h-16 shrink-0 items-center gap-3 border-b border-slate-200 bg-white/85 px-4 backdrop-blur sm:px-6 lg:px-8 dark:border-slate-800 dark:bg-slate-900/85">
    <button type="button" class="btn btn-ghost btn-icon -ml-2 lg:hidden" x-on:click="sidebar = true"
        aria-label="Open menu" aria-controls="sidebar" :aria-expanded="sidebar">
        <x-icon name="menu-2" />
    </button>

    <div class="flex-1"></div>

    {{-- Theme: light / dark / follow the system --}}
    <div x-data="{ open: false }" class="relative" x-on:click.outside="open = false">
        <button type="button" class="btn btn-ghost btn-icon" x-on:click="open = !open" aria-label="Change theme" :aria-expanded="open">
            <span x-show="$store.theme.mode === 'light'"><x-icon name="sun" /></span>
            <span x-show="$store.theme.mode === 'dark'" x-cloak><x-icon name="moon" /></span>
            <span x-show="$store.theme.mode === 'system'" x-cloak><x-icon name="device-desktop" /></span>
        </button>
        <div x-show="open" x-transition.origin.top.right x-cloak class="dropdown-panel right-0 !min-w-[10rem]">
            @foreach(['light' => ['Light', 'sun'], 'dark' => ['Dark', 'moon'], 'system' => ['System', 'device-desktop']] as $mode => [$label, $icon])
                <button type="button" class="dropdown-item" x-on:click="$store.theme.set('{{ $mode }}'); open = false"
                    :class="$store.theme.mode === '{{ $mode }}' && 'text-brand-700 dark:text-brand-300 font-semibold'">
                    <x-icon :name="$icon" class="size-4" /> {{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    {{-- Account --}}
    <div x-data="{ open: false }" class="relative" x-on:click.outside="open = false" x-on:keydown.escape="open = false">
        <button type="button" class="flex items-center gap-2 rounded-full p-0.5 pr-2 hover:bg-slate-100 dark:hover:bg-slate-800"
            x-on:click="open = !open" aria-label="Account menu" :aria-expanded="open">
            <span class="flex size-8 items-center justify-center rounded-full bg-brand-600 text-xs font-semibold text-white dark:bg-brand-500 dark:text-slate-950">
                {{ strtoupper(mb_substr($user->first_name ?: $user->username, 0, 1) . mb_substr($user->last_name ?? '', 0, 1)) }}
            </span>
            <x-icon name="chevron-down" class="hidden size-4 text-slate-400 sm:block" />
        </button>
        <div x-show="open" x-transition.origin.top.right x-cloak class="dropdown-panel right-0 w-64">
            <div class="border-b border-slate-100 px-3 pb-3 pt-2 dark:border-slate-800">
                <p class="truncate text-sm font-semibold text-slate-900 dark:text-white">{{ trim($user->first_name . ' ' . $user->last_name) ?: $user->username }}</p>
                <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $user->email ?: '@' . $user->username }}</p>
                @if($user->company)
                    <p class="mt-1 truncate text-xs text-slate-500 dark:text-slate-400">{{ $user->company->name }}</p>
                @endif
            </div>
            <div class="pt-1">
                @livewire('auth.logout')
            </div>
        </div>
    </div>
</header>
