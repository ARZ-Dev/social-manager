{{--
    Server-driven modal: render it only while it should be open.
    <x-ui.modal close="closeModal" title="Edit role" size="3xl"> ... <x-slot:footer>…</x-slot:footer> </x-ui.modal>

    It animates in when rendered and out when closed. Inside, close with
    x-on:click="close()" (Cancel buttons etc.): the exit animation starts at once
    and the server is told in parallel. When the server closes it (e.g. after a
    save), resources/js/app.js holds the element back until the animation ends.
--}}
@props(['close', 'title' => null, 'size' => '2xl'])
@php
    $width = [
        'md' => 'sm:max-w-md', 'lg' => 'sm:max-w-lg', 'xl' => 'sm:max-w-xl', '2xl' => 'sm:max-w-2xl',
        '3xl' => 'sm:max-w-3xl', '4xl' => 'sm:max-w-4xl', '5xl' => 'sm:max-w-5xl',
    ][$size] ?? 'sm:max-w-2xl';
@endphp

<div data-ui-modal
    x-data="{
        show: false,
        closedAt: null,
        init() {
            document.body.classList.add('overflow-hidden');
            requestAnimationFrame(() => this.show = true);
        },
        destroy() {
            document.body.classList.remove('overflow-hidden');
        },
        close() {
            if (this.closedAt) return;
            this.closedAt = Date.now();
            this.show = false;
            $wire.{{ $close }}();
        },
    }"
    x-on:keydown.escape.window="close()"
    class="fixed inset-0 z-50 flex items-end justify-center sm:items-center sm:p-6" role="dialog" aria-modal="true"
    @if($title) aria-label="{{ $title }}" @endif>
    <div x-show="show" x-on:click="close()"
        x-transition:enter="transition-opacity duration-200 ease-out" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity duration-150 ease-in" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-slate-900/50 backdrop-blur-[2px]"></div>

    <div x-show="show"
        x-transition:enter="transition duration-200 ease-out"
        x-transition:enter-start="translate-y-6 opacity-0 sm:translate-y-2 sm:scale-95"
        x-transition:enter-end="translate-y-0 opacity-100 sm:scale-100"
        x-transition:leave="transition duration-150 ease-in"
        x-transition:leave-start="translate-y-0 opacity-100 sm:scale-100"
        x-transition:leave-end="translate-y-6 opacity-0 sm:translate-y-2 sm:scale-95"
        {{ $attributes->merge(['class' => "relative flex max-h-[92vh] w-full flex-col rounded-t-2xl bg-white shadow-xl sm:rounded-2xl dark:bg-slate-900 dark:ring-1 dark:ring-slate-800 $width"]) }}>
        @if($title || isset($header))
            <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                <div class="min-w-0 flex-1">
                    @isset($header)
                        {{ $header }}
                    @else
                        <h2 class="text-base font-semibold">{{ $title }}</h2>
                    @endisset
                </div>
                <button type="button" class="btn btn-ghost btn-icon btn-sm -mr-1" x-on:click="close()" aria-label="Close">
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
