{{-- Global confirmation dialog: $dispatch('confirm', { title, text, confirm, danger, onConfirm }) --}}
<div x-data="confirmDialog" x-on:confirm.window="open($event.detail)" x-on:keydown.escape.window="show = false"
    x-show="show" x-cloak class="fixed inset-0 z-[70] flex items-end justify-center p-4 sm:items-center"
    role="alertdialog" aria-modal="true" aria-labelledby="confirm-title" aria-describedby="confirm-text">
    <div x-show="show" x-transition.opacity class="fixed inset-0 bg-slate-900/50" x-on:click="show = false"></div>

    <div x-show="show" x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="translate-y-4 opacity-0 sm:translate-y-0 sm:scale-95"
        x-transition:leave="transition duration-150 ease-in" x-transition:leave-end="opacity-0"
        class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-xl dark:bg-slate-900 dark:ring-1 dark:ring-slate-800">
        <div class="flex gap-4">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-full"
                :class="danger ? 'bg-red-100 text-red-600 dark:bg-red-500/15 dark:text-red-400' : 'bg-brand-100 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300'">
                <x-icon name="alert-triangle" />
            </span>
            <div class="min-w-0">
                <h2 id="confirm-title" class="text-base font-semibold" x-text="title"></h2>
                <p id="confirm-text" class="mt-1 text-sm text-slate-600 dark:text-slate-400" x-text="text"></p>
            </div>
        </div>
        <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <button type="button" class="btn btn-secondary" x-on:click="show = false">Cancel</button>
            <button type="button" class="btn" :class="danger ? 'btn-danger' : 'btn-primary'" x-ref="confirmButton" x-on:click="confirm()" x-text="confirmText"></button>
        </div>
    </div>
</div>
