{{-- Account picture (falls back to initials) with a small platform badge. Expects $acc, optional $size. --}}
@php
    $box = ['sm' => 'size-8 text-[11px]', 'md' => 'size-10 text-xs', 'lg' => 'size-12 text-sm'][$size ?? 'md'];
    $badge = ($size ?? 'md') === 'sm' ? 'size-3.5' : 'size-4';
    $p = $platforms[$acc->platform];
    $initials = strtoupper(mb_substr(ltrim($acc->display_name, '@'), 0, 2));
@endphp
<span class="relative inline-flex shrink-0 {{ $box }}">
    <span class="relative flex size-full items-center justify-center overflow-hidden rounded-full bg-slate-100 font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-300">
        {{ $initials }}
        @if($acc->picture_url)
            <img src="{{ $acc->picture_url }}" alt="" loading="lazy" class="absolute inset-0 size-full object-cover" onerror="this.remove()">
        @endif
    </span>
    <span class="absolute -bottom-0.5 -right-0.5 flex {{ $badge }} items-center justify-center rounded-full text-white ring-2 ring-white dark:ring-slate-900 {{ $p['dot'] }}" title="{{ $p['label'] }}">
        <x-icon :name="$p['icon']" class="size-2.5" />
    </span>
</span>
