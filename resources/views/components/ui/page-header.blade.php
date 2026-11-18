@props(['title', 'subtitle' => null, 'back' => null])

<div {{ $attributes->merge(['class' => 'mb-6 flex flex-wrap items-end justify-between gap-4']) }}>
    <div class="min-w-0">
        @if($back)
            <a href="{{ $back }}" wire:navigate class="mb-2 inline-flex items-center gap-1 text-sm font-medium text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white">
                <x-icon name="arrow-left" class="size-4" /> Back
            </a>
        @endif
        <h1 class="truncate text-2xl font-semibold tracking-tight">{{ $title }}</h1>
        @if($subtitle)
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $subtitle }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
