{{--
    Server-driven modal: render it only while it should be open.
    <x-ui.modal close="closeModal" title="Edit role" size="3xl"> ... <x-slot:footer>…</x-slot:footer> </x-ui.modal>
--}}
@props(['close', 'title' => null, 'size' => '2xl'])
@php
    $width = [
        'md' => 'sm:max-w-md', 'lg' => 'sm:max-w-lg', 'xl' => 'sm:max-w-xl', '2xl' => 'sm:max-w-2xl',
        '3xl' => 'sm:max-w-3xl', '4xl' => 'sm:max-w-4xl', '5xl' => 'sm:max-w-5xl',
    ][$size] ?? 'sm:max-w-2xl';
@endphp

<div x-data="{ init() { document.body.classList.add('overflow-hidden') }, destroy() { document.body.classList.remove('overflow-hidden') } }"
    x-on:keydown.escape.window="$wire.{{ $close }}()"
    class="fixed inset-0 z-50 flex items-end justify-center sm:items-center sm:p-6" role="dialog" aria-modal="true"
    @if($title) aria-label="{{ $title }}" @endif>
    <div class="fixed inset-0 bg-slate-900/50" wire:click="{{ $close }}"></div>

    <div {{ $attributes->merge(['class' => "relative flex max-h-[92vh] w-full flex-col rounded-t-2xl bg-white shadow-xl sm:rounded-2xl dark:bg-slate-900 dark:ring-1 dark:ring-slate-800 $width"]) }}>
        @if($title || isset($header))
            <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                <div class="min-w-0 flex-1">
                    @isset($header)
                        {{ $header }}
                    @else
                        <h2 class="text-base font-semibold">{{ $title }}</h2>
                    @endisset
                </div>
                <button type="button" class="btn btn-ghost btn-icon btn-sm -mr-1" wire:click="{{ $close }}" aria-label="Close">
                    <x-icon name="x" />
                </button>
            </div>
        @endif

        <div class="flex-1 overflow-y-auto px-5 py-5">
            {{ $slot }}
        </div>

        @isset($footer)
            <div class="flex flex-col-reverse gap-2 border-t border-slate-200 px-5 py-4 sm:flex-row sm:justify-end dark:border-slate-800">
                {{ $footer }}
            </div>
        @endisset
    </div>
</div>
