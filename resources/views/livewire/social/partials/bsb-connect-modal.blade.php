<x-ui.modal close="closeBsbConnect" title="Connect BSB (SMS & WhatsApp)" size="lg">
    <form id="bsb-form" wire:submit="connectBsb" class="space-y-4" novalidate>
        <p class="text-sm text-slate-600 dark:text-slate-400">
            Use the API key and secret from your BSB account: log in at
            <a href="https://www.bestsmsbulk.com/ai-login/ai-marketing-login" target="_blank" rel="noopener" class="link">bestsmsbulk.com</a>
            and open <strong>MANAGE APIs</strong> (Integration → API keys).
        </p>

        <div>
            <label class="label" for="bsb-name">Name <span class="font-normal text-slate-400">(optional)</span></label>
            <input wire:model="bsbName" type="text" id="bsb-name" class="input" placeholder="e.g. Main BSB account" maxlength="100" autocomplete="off">
            @error('bsbName') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="label" for="bsb-key">API key</label>
            <input wire:model="bsbKey" type="text" id="bsb-key" @class(['input font-mono', 'input-error' => $errors->has('bsbKey')]) autocomplete="off" spellcheck="false">
            @error('bsbKey') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div x-data="{ show: false }">
            <label class="label" for="bsb-secret">API secret</label>
            <div class="relative">
                <input wire:model="bsbSecret" :type="show ? 'text' : 'password'" type="password" id="bsb-secret"
                    @class(['input pr-10 font-mono', 'input-error' => $errors->has('bsbSecret')]) autocomplete="new-password" spellcheck="false">
                <button type="button" class="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                    x-on:click="show = !show" :aria-label="show ? 'Hide secret' : 'Show secret'">
                    <span x-show="!show"><x-icon name="eye" class="size-4" /></span>
                    <span x-show="show" x-cloak><x-icon name="eye-off" class="size-4" /></span>
                </button>
            </div>
            @error('bsbSecret') <p class="field-error">{{ $message }}</p> @enderror
            <p class="help">Checked with BSB before saving, then stored encrypted. Connecting the same key again updates it.</p>
        </div>

        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-xs text-slate-600 dark:border-slate-800 dark:bg-slate-800/40 dark:text-slate-400">
            <p class="font-medium text-slate-800 dark:text-slate-200">What the app can read from BSB</p>
            <ul class="mt-1 list-disc space-y-0.5 pl-4">
                <li>Balance, and every message's recipient, channel (SMS / WhatsApp) and delivery status.</li>
                <li>Only messages sent <em>today</em>: the app checks every {{ config('bsb.poll_minutes') }} minutes and keeps its own log from now on.</li>
                <li>Not the message text, sender ID or replies: BSB's API doesn't offer them.</li>
            </ul>
        </div>
    </form>

    <x-slot:footer>
        <button type="button" class="btn btn-secondary" x-on:click="close()">Cancel</button>
        <button type="submit" form="bsb-form" class="btn btn-primary" wire:loading.attr="disabled" wire:target="connectBsb">
            <span wire:loading wire:target="connectBsb" class="size-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
            <x-icon name="plug-connected" class="size-4" wire:loading.remove wire:target="connectBsb" /> Check and connect
        </button>
    </x-slot:footer>
</x-ui.modal>
