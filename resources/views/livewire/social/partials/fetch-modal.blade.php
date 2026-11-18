@php
    $groupLabels = ['facebook' => 'Facebook Pages', 'instagram' => 'Instagram accounts', 'ads' => 'Ad accounts'];
    $selected = collect($fetchAccounts)->map(fn ($id) => (int) $id);
    $groups = $accounts->groupBy('platform');
    $allOn = $accounts->every(fn ($a) => $selected->contains($a->id));
    $picked = $accounts->filter(fn ($a) => $selected->contains($a->id))->count();
    $periodStart = $fetchPeriod === 'custom'
        ? (strtotime($fetchFrom) ? \Illuminate\Support\Carbon::parse($fetchFrom) : null)
        : now()->subDays((int) $fetchPeriod);
@endphp

<x-ui.modal close="closeFetch" title="Fetch data from Meta" size="2xl">
    <form id="fetch-form" wire:submit="startFetch" class="space-y-6" novalidate>
        {{-- Accounts, grouped by platform --}}
        <fieldset>
            <div class="mb-2 flex items-center justify-between gap-3">
                <legend class="text-sm font-semibold text-slate-900 dark:text-white">Accounts</legend>
                <button type="button" class="link text-xs" wire:click="toggleFetchGroup">{{ $allOn ? 'Clear all' : 'Select all' }}</button>
            </div>

            <div class="space-y-3">
                @foreach($groups as $platform => $list)
                    @php
                        $groupOn = $list->every(fn ($a) => $selected->contains($a->id));
                        $groupCount = $list->filter(fn ($a) => $selected->contains($a->id))->count();
                    @endphp
                    <div class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800" wire:key="fetch-group-{{ $platform }}">
                        <div class="flex items-center justify-between gap-3 bg-slate-50 px-4 py-2 dark:bg-slate-800/50">
                            <label class="flex cursor-pointer items-center gap-2.5 text-sm font-medium text-slate-900 dark:text-white">
                                <input type="checkbox" class="checkbox" wire:click="toggleFetchGroup('{{ $platform }}')" @checked($groupOn)
                                    aria-label="Select all {{ $groupLabels[$platform] }}">
                                <x-icon :name="$platforms[$platform]['icon']" class="size-4 text-slate-500 dark:text-slate-400" />
                                {{ $groupLabels[$platform] }}
                            </label>
                            <span class="text-xs tabular-nums text-slate-500 dark:text-slate-400">{{ $groupCount }} of {{ $list->count() }}</span>
                        </div>
                        <div class="max-h-56 divide-y divide-slate-100 overflow-y-auto scrollbar-thin dark:divide-slate-800">
                            @foreach($list as $acc)
                                <label class="flex cursor-pointer items-center gap-3 px-4 py-2.5 transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/40" wire:key="fetch-acc-{{ $acc->id }}">
                                    <input type="checkbox" class="checkbox" value="{{ $acc->id }}" wire:model.live="fetchAccounts">
                                    @include('livewire.social.partials.avatar', ['acc' => $acc, 'size' => 'sm'])
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm font-medium text-slate-900 dark:text-white">{{ $acc->display_name }}</span>
                                        <span class="block truncate text-xs text-slate-500 dark:text-slate-400">
                                            @if($acc->last_synced_at)
                                                Fetched {{ $acc->last_synced_at->diffForHumans() }}@isset($acc->meta['last_fetch_since']), from {{ \Illuminate\Support\Carbon::parse($acc->meta['last_fetch_since'])->format('j M Y') }}@endisset
                                            @else
                                                Never fetched
                                            @endif
                                        </span>
                                    </span>
                                    @if($acc->last_sync_error)
                                        <x-icon name="alert-triangle" class="size-4 shrink-0 text-amber-500" aria-label="Last fetch had warnings" />
                                    @endif
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
            @error('fetchAccounts') <p class="field-error">{{ $message }}</p> @enderror
            @error('fetchAccounts.*') <p class="field-error">{{ $message }}</p> @enderror
        </fieldset>

        {{-- Period --}}
        <fieldset>
            <legend class="mb-2 text-sm font-semibold text-slate-900 dark:text-white">Period</legend>
            <div class="grid grid-cols-3 gap-2 sm:grid-cols-6">
                @foreach(\App\Models\Social\SocialAccount::FETCH_PERIODS as $value => $label)
                    <label class="cursor-pointer">
                        <input type="radio" class="peer sr-only" name="fetch-period" value="{{ $value }}" wire:model.live="fetchPeriod">
                        <span class="flex h-10 items-center justify-center rounded-lg border border-slate-200 text-sm font-medium text-slate-600 transition-colors hover:border-slate-300 peer-checked:border-brand-500 peer-checked:bg-brand-50 peer-checked:text-brand-700 peer-focus-visible:ring-2 peer-focus-visible:ring-brand-500 dark:border-slate-700 dark:text-slate-300 dark:peer-checked:border-brand-400 dark:peer-checked:bg-brand-500/10 dark:peer-checked:text-brand-200">
                            {{ $label }}
                        </span>
                    </label>
                @endforeach
            </div>

            @if($fetchPeriod === 'custom')
                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <label for="fetch-from" class="text-sm text-slate-700 dark:text-slate-300">From</label>
                    <input type="date" id="fetch-from" wire:model.live="fetchFrom"
                        min="{{ now()->subDays(\App\Models\Social\SocialAccount::FETCH_MAX_DAYS)->toDateString() }}" max="{{ now()->toDateString() }}"
                        @class(['input w-auto', 'input-error' => $errors->has('fetchFrom')])>
                    <span class="text-sm text-slate-500 dark:text-slate-400">to today</span>
                </div>
                @error('fetchFrom') <p class="field-error">{{ $message }}</p> @enderror
            @endif

            <p class="help">
                @if($periodStart)
                    <span class="font-medium text-slate-700 dark:text-slate-300">{{ $periodStart->format('j M Y') }} → today.</span>
                @endif
                Longer periods take longer. Audience demographics are always the latest snapshot, and ad results cover the period you pick.
            </p>
        </fieldset>
    </form>

    <x-slot:footer>
        <button type="button" class="btn btn-secondary" x-on:click="close()">Cancel</button>
        <button type="submit" form="fetch-form" class="btn btn-primary" @disabled($picked === 0) wire:loading.attr="disabled" wire:target="startFetch">
            <x-icon name="refresh" class="size-4" />
            Fetch {{ $picked === $accounts->count() ? 'all ' . $picked : $picked }} {{ str('account')->plural($picked) }}
        </button>
    </x-slot:footer>
</x-ui.modal>
