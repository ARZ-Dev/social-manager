@props(['icon' => 'inbox', 'title', 'compact' => false])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center text-center ' . ($compact ? 'px-4 py-8' : 'px-6 py-14')]) }}>
    <span class="mb-4 flex size-12 items-center justify-center rounded-full bg-slate-100 text-slate-400 dark:bg-slate-800 dark:text-slate-500">
        <x-icon :name="$icon" class="size-6" />
    </span>
    <h3 class="text-sm font-semibold">{{ $title }}</h3>
    @if($slot->hasActualContent())
        <div class="mt-1 max-w-md text-sm text-slate-500 dark:text-slate-400">{{ $slot }}</div>
    @endif
    @isset($action)
        <div class="mt-5">{{ $action }}</div>
    @endisset
</div>
