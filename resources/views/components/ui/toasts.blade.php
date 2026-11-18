@php
    // Flash messages from redirects become toasts too.
    $flash = collect(['success', 'error', 'warning', 'info'])
        ->filter(fn ($type) => session()->has($type))
        ->map(fn ($type) => ['type' => $type, 'title' => session($type)])
        ->values();
@endphp

<div x-data="toasts(@js($flash))"
    x-on:swal:success.window="push('success', $event.detail)"
    x-on:swal:warning.window="push('warning', $event.detail)"
    x-on:swal:error.window="push('error', $event.detail)"
    x-on:swal:info.window="push('info', $event.detail)"
    class="pointer-events-none fixed inset-x-0 top-0 z-[60] flex flex-col items-center gap-2 p-4 sm:items-end sm:p-6"
    aria-live="polite" aria-atomic="false">
    <template x-for="toast in items" :key="toast.id">
        <div x-show="toast.visible" x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="translate-y-2 opacity-0"
            x-transition:leave="transition duration-150 ease-in" x-transition:leave-end="opacity-0"
            class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-lg dark:border-slate-700 dark:bg-slate-900"
            :role="toast.type === 'error' ? 'alert' : 'status'">
            <span class="mt-0.5 shrink-0"
                :class="{
                    'text-emerald-500': toast.type === 'success',
                    'text-amber-500': toast.type === 'warning',
                    'text-red-500': toast.type === 'error',
                    'text-brand-500': toast.type === 'info',
                }">
                <template x-if="toast.type === 'success'"><x-icon name="circle-check" /></template>
                <template x-if="toast.type === 'warning'"><x-icon name="alert-triangle" /></template>
                <template x-if="toast.type === 'error'"><x-icon name="alert-circle" /></template>
                <template x-if="toast.type === 'info'"><x-icon name="info-circle" /></template>
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-slate-900 dark:text-white" x-text="toast.title"></p>
                <p class="mt-0.5 text-sm text-slate-600 dark:text-slate-400" x-show="toast.text" x-text="toast.text"></p>
            </div>
            <button type="button" class="-m-1 rounded-md p-1 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200" x-on:click="dismiss(toast.id)" aria-label="Dismiss">
                <x-icon name="x" class="size-4" />
            </button>
        </div>
    </template>
</div>
