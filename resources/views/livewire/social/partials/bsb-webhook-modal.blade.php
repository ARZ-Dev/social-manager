{{-- Setup of a BSB account's WhatsApp chats webhook. Expects $acc. --}}
@php
    $url = $acc->bsbWebhookUrl();
    $signed = filled($acc->webhook_secret);
@endphp

<x-ui.modal close="closeWebhookSetup" :title="'WhatsApp chats · ' . $acc->display_name" size="2xl">
    <div class="space-y-5">
        {{-- Status: polled, so a "Send test" from BSB shows up here --}}
        <div class="flex flex-wrap items-center gap-2" wire:poll.10s>
            @if($acc->webhook_last_at)
                <span class="badge badge-green"><x-icon name="circle-check" class="size-3.5" /> Receiving</span>
                <span class="text-sm text-slate-600 dark:text-slate-300">
                    Last delivery {{ $acc->webhook_last_at->diffForHumans() }}{{ $acc->webhook_last_event ? ' (' . $acc->webhook_last_event . ')' : '' }}
                </span>
            @else
                <span class="badge badge-gray"><x-icon name="clock" class="size-3.5" /> Waiting for BSB</span>
                <span class="text-sm text-slate-600 dark:text-slate-300">Nothing received yet.</span>
            @endif
            @if($signed)
                <span class="badge badge-green"><x-icon name="shield-lock" class="size-3.5" /> Signature checked</span>
            @else
                <span class="badge badge-yellow"><x-icon name="alert-triangle" class="size-3.5" /> No signing secret yet</span>
            @endif
        </div>

        <ol class="space-y-4 text-sm text-slate-700 dark:text-slate-300">
            <li class="flex gap-3">
                <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-slate-900 text-xs font-semibold text-white dark:bg-white dark:text-slate-900">1</span>
                <div class="min-w-0 flex-1">
                    In BSB, open <strong>Developers → WhatsApp Messaging API</strong> and paste this as the <strong>Webhook URL</strong>:
                    <div class="mt-2 flex gap-2" x-data="{ copied: false }">
                        <input type="text" class="input font-mono text-xs" value="{{ $url }}" readonly aria-label="Webhook URL">
                        <button type="button" class="btn btn-secondary shrink-0" x-on:click="navigator.clipboard.writeText(@js($url)); copied = true; setTimeout(() => copied = false, 2000)">
                            <span x-show="!copied"><x-icon name="copy" class="size-4" /></span>
                            <span x-show="copied" x-cloak><x-icon name="check" class="size-4" /></span>
                            <span x-text="copied ? 'Copied' : 'Copy'">Copy</span>
                        </button>
                    </div>
                    @unless(str_starts_with($url, 'https://'))
                        <p class="field-error">BSB only sends to public HTTPS addresses. Use the URL of the live site, not this local one.</p>
                    @endunless
                </div>
            </li>
            <li class="flex gap-3">
                <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-slate-900 text-xs font-semibold text-white dark:bg-white dark:text-slate-900">2</span>
                <div>Tick <strong>Enable webhook delivery</strong>, <strong>Notify on incoming messages</strong> and <strong>Notify on message status changes</strong>, then click <strong>Save</strong>.</div>
            </li>
            <li class="flex gap-3">
                <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-slate-900 text-xs font-semibold text-white dark:bg-white dark:text-slate-900">3</span>
                <form wire:submit="saveWebhookSecret" class="min-w-0 flex-1" novalidate>
                    BSB then shows a <strong>Webhook signing secret</strong>. Paste it here so only messages really sent by BSB are accepted:
                    <div class="mt-2 flex gap-2" x-data="{ show: false }">
                        <div class="relative flex-1">
                            <input wire:model="webhookSecret" :type="show ? 'text' : 'password'" type="password" autocomplete="new-password" spellcheck="false"
                                @class(['input pr-10 font-mono', 'input-error' => $errors->has('webhookSecret')])
                                placeholder="{{ $signed ? 'Saved. Paste a new one to replace it' : 'Signing secret' }}" aria-label="Webhook signing secret">
                            <button type="button" class="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                                x-on:click="show = !show" :aria-label="show ? 'Hide secret' : 'Show secret'">
                                <span x-show="!show"><x-icon name="eye" class="size-4" /></span>
                                <span x-show="show" x-cloak><x-icon name="eye-off" class="size-4" /></span>
                            </button>
                        </div>
                        <button type="submit" class="btn btn-primary shrink-0" wire:loading.attr="disabled" wire:target="saveWebhookSecret">Save</button>
                    </div>
                    @error('webhookSecret') <p class="field-error">{{ $message }}</p> @enderror
                    <p class="help">Stored encrypted. If you click <strong>Rotate secret</strong> in BSB, paste the new one here too.</p>
                </form>
            </li>
            <li class="flex gap-3">
                <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-slate-900 text-xs font-semibold text-white dark:bg-white dark:text-slate-900">4</span>
                <div>Click <strong>Send test</strong> in BSB. "Last delivery" above updates within a few seconds.</div>
            </li>
        </ol>

        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-xs text-slate-600 dark:border-slate-800 dark:bg-slate-800/40 dark:text-slate-400">
            <p class="font-medium text-slate-800 dark:text-slate-200">What shows up in Messages</p>
            <ul class="mt-1 list-disc space-y-0.5 pl-4">
                <li>Every WhatsApp message customers send, with photos, voice notes, videos and documents, from now on. BSB doesn't send older chats.</li>
                <li>Every message sent from BSB, with its delivery and read status but without its text: BSB's webhook doesn't include it.</li>
                <li>Messages to numbers that never wrote to you (order confirmations, campaigns) get their own chat too.</li>
            </ul>
        </div>
    </div>

    <x-slot:footer>
        <button type="button" class="btn btn-secondary" x-on:click="close()">Close</button>
    </x-slot:footer>
</x-ui.modal>
