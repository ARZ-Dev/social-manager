@php
    $user = auth()->user();
    $sections = [
        null => [
            ['route' => 'dashboard', 'match' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'layout-dashboard', 'can' => null],
        ],
        'Workspace' => [
            ['route' => 'social', 'match' => 'social*', 'label' => 'Social Media', 'icon' => 'brand-instagram', 'can' => 'social-list'],
            ['route' => 'companies', 'match' => 'companies*', 'label' => 'Companies', 'icon' => 'building', 'can' => 'company-list'],
        ],
        'Administration' => [
            ['route' => 'users', 'match' => 'users*', 'label' => 'Users', 'icon' => 'users', 'can' => 'user-list'],
            ['route' => 'roles', 'match' => 'roles', 'label' => 'Roles', 'icon' => 'shield-lock', 'can' => 'role-list'],
            ['route' => 'permissions', 'match' => 'permissions', 'label' => 'Permissions', 'icon' => 'key', 'can' => 'permission-list'],
        ],
    ];
@endphp

<div class="flex h-16 shrink-0 items-center justify-between gap-2 border-b border-slate-200 px-5 dark:border-slate-800">
    <a href="{{ route('dashboard') }}" wire:navigate class="flex min-w-0 items-center gap-2.5">
        <img src="{{ asset('farm_assets/images/farm_images/logo.png') }}" alt="SocialM" class="h-8 w-auto max-w-[10rem] object-contain dark:brightness-0 dark:invert">
    </a>
    <button type="button" class="btn btn-ghost btn-icon btn-sm lg:hidden" x-on:click="sidebar = false" aria-label="Close menu">
        <x-icon name="x" />
    </button>
</div>

<nav class="flex-1 space-y-6 overflow-y-auto px-3 py-5 scrollbar-thin" aria-label="Main">
    @foreach($sections as $heading => $items)
        @php $items = array_filter($items, fn ($i) => !$i['can'] || $user->can($i['can'])); @endphp
        @continue(empty($items))
        <div>
            @if($heading)
                <p class="mb-2 px-3 text-[11px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ $heading }}</p>
            @endif
            <ul class="space-y-0.5">
                @foreach($items as $item)
                    @php $active = request()->is($item['match']); @endphp
                    <li>
                        <a href="{{ route($item['route']) }}" wire:navigate
                            @class([
                                'group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                                'bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-300' => $active,
                                'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white' => !$active,
                            ])
                            @if($active) aria-current="page" @endif>
                            <x-icon :name="$item['icon']" :class="\Illuminate\Support\Arr::toCssClasses([
                                'size-5 shrink-0',
                                'text-brand-600 dark:text-brand-400' => $active,
                                'text-slate-400 group-hover:text-slate-600 dark:text-slate-500 dark:group-hover:text-slate-300' => !$active,
                            ])" />
                            {{ $item['label'] }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endforeach
</nav>

<div class="border-t border-slate-200 p-3 dark:border-slate-800">
    <div class="flex items-center gap-3 rounded-lg px-2 py-2">
        <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-brand-100 text-sm font-semibold text-brand-700 dark:bg-brand-500/20 dark:text-brand-300">
            {{ strtoupper(mb_substr($user->first_name ?: $user->username, 0, 1) . mb_substr($user->last_name ?? '', 0, 1)) }}
        </span>
        <div class="min-w-0">
            <p class="truncate text-sm font-medium text-slate-900 dark:text-white">{{ trim($user->first_name . ' ' . $user->last_name) ?: $user->username }}</p>
            <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $user->roles->first()?->name ?? $user->username }}</p>
        </div>
    </div>
</div>
