@php $hasAlerts = $syncing || !$metaConfigured || $neverFetched || $expiringAds->isNotEmpty() || $warningAccounts->isNotEmpty(); @endphp

<div @class(['space-y-3', 'mb-6' => $hasAlerts])>
    {{-- Background fetch: every 5 s ask the server for each account's state; it re-renders when all are done.
         A check that fails or hangs is skipped (see resources/js/app.js) and the next one tries again. --}}
    @if($syncing)
        @php
            $finishedNow = collect($syncStates)->whereIn('state', ['done', 'failed', 'stopped'])->count();
            $percentNow = count($syncStates) ? (int) floor(collect($syncStates)->avg('progress')) : 0;
        @endphp
        <div class="overflow-hidden rounded-xl border border-brand-200 bg-brand-50 dark:border-brand-500/20 dark:bg-brand-500/10"
            role="status" wire:key="sync-progress"
            x-on:sync-check-failed.window="busy = false"
            x-data="{
                left: {{ $syncLeft }},
                states: @js((object) $syncStates),
                open: true,
                busy: false,
                timer: null,
                get total() { return Object.keys(this.states).length },
                get finished() { return Object.values(this.states).filter((s) => ['done', 'failed', 'stopped'].includes(s.state)).length },
                // Whole run: the average of every account's progress.
                get percent() { return this.total ? Math.floor(Object.values(this.states).reduce((sum, s) => sum + (s.progress ?? 0), 0) / this.total) : 0 },
                progress(id) { return this.states[id]?.progress ?? 0 },
                state(id) { return this.states[id]?.state ?? 'queued' },
                note(id) { return this.states[id]?.note ?? '' },
                warn(id) { return !!this.states[id]?.warn },
                init() {
                    this.timer = setInterval(async () => {
                        if (this.busy) return;
                        this.busy = true;
                        try {
                            const res = await Promise.race([
                                $wire.checkSync(),
                                new Promise((resolve) => setTimeout(() => resolve(null), 20000)),
                            ]);
                            if (res && Number.isInteger(res.left)) {
                                this.left = res.left;
                                if (res.states && Object.keys(res.states).length) this.states = res.states;
                            }
                        } catch (e) {
                            // Network hiccup: try again on the next tick.
                        } finally {
                            this.busy = false;
                        }
                    }, 5000);
                },
                destroy() { clearInterval(this.timer); },
            }">
            <div class="flex flex-wrap items-center gap-3 p-4 text-sm text-brand-900 dark:text-brand-100">
                <span class="size-5 shrink-0 animate-spin rounded-full border-2 border-brand-200 border-t-brand-600 dark:border-brand-500/30 dark:border-t-brand-300"></span>
                <div class="min-w-[12rem] flex-1">
                    <p class="flex flex-wrap items-baseline gap-x-2 font-semibold">
                        Fetching data from Meta…
                        <span class="font-normal opacity-80"><span x-text="finished">{{ $finishedNow }}</span> of <span x-text="total">{{ count($syncStates) }}</span> accounts done</span>
                        <span class="ml-auto tabular-nums" x-text="`${percent}%`">{{ $percentNow }}%</span>
                    </p>
                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-brand-100 dark:bg-brand-500/20"
                        role="progressbar" aria-label="Fetch progress" aria-valuemin="0" aria-valuemax="100"
                        aria-valuenow="{{ $percentNow }}" :aria-valuenow="percent">
                        <div class="h-full rounded-full bg-brand-500 transition-[width] duration-500"
                            style="width: {{ $percentNow }}%"
                            :style="`width: ${percent}%`"></div>
                    </div>
                    <p class="mt-1.5 text-xs opacity-80">It runs in the background, so you can keep working or leave this page.</p>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" class="btn btn-ghost btn-sm text-brand-800 hover:bg-brand-100 dark:text-brand-200 dark:hover:bg-brand-500/10"
                        x-on:click="open = !open" :aria-expanded="open" aria-controls="sync-run-list">
                        <span x-text="open ? 'Hide accounts' : 'Show accounts'">Hide accounts</span>
                        <x-icon name="chevron-down" class="size-4 transition-transform" ::class="open && 'rotate-180'" />
                    </button>
                    @can('social-sync')
                        <button type="button" wire:click="stopFetch" wire:loading.attr="disabled" wire:target="stopFetch" class="btn btn-secondary btn-sm">
                            <x-icon name="player-stop" class="size-4" /> Stop
                        </button>
                    @endcan
                </div>
            </div>

            {{-- One row per account of the run, in fetch order --}}
            <ul id="sync-run-list" x-show="open" class="max-h-80 divide-y divide-brand-100 overflow-y-auto border-t border-brand-200 bg-white scrollbar-thin dark:divide-slate-800 dark:border-brand-500/20 dark:bg-slate-900">
                @foreach($syncRun as $acc)
                    @php $initial = $syncStates[$acc->id] ?? ['state' => 'queued', 'note' => '', 'warn' => false, 'progress' => 0]; @endphp
                    <li class="flex items-center gap-3 px-4 py-2.5" wire:key="run-{{ $acc->id }}">
                        @include('livewire.social.partials.avatar', ['acc' => $acc, 'size' => 'sm'])
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-slate-900 dark:text-white">{{ $acc->display_name }}</p>
                            <p class="truncate text-xs text-slate-500 dark:text-slate-400" x-text="note({{ $acc->id }})">{{ $initial['note'] }}</p>
                            {{-- This account's own progress while it's being fetched --}}
                            <div x-show="state({{ $acc->id }}) === 'running'" @if($initial['state'] !== 'running') x-cloak @endif
                                class="mt-1.5 h-1 max-w-xs overflow-hidden rounded-full bg-brand-100 dark:bg-brand-500/20"
                                role="progressbar" aria-label="{{ $acc->display_name }} progress" aria-valuemin="0" aria-valuemax="100"
                                aria-valuenow="{{ $initial['progress'] }}" :aria-valuenow="progress({{ $acc->id }})">
                                <div class="h-full rounded-full bg-brand-500 transition-[width] duration-500"
                                    style="width: {{ $initial['progress'] }}%" :style="`width: ${progress({{ $acc->id }})}%`"></div>
                            </div>
                        </div>
                        <span class="shrink-0">
                            <span x-show="state({{ $acc->id }}) === 'done' && !warn({{ $acc->id }})" @if($initial['state'] !== 'done' || $initial['warn']) x-cloak @endif class="badge badge-green">
                                <x-icon name="circle-check" class="size-3.5" /> Done
                            </span>
                            <span x-show="state({{ $acc->id }}) === 'done' && warn({{ $acc->id }})" @if($initial['state'] !== 'done' || !$initial['warn']) x-cloak @endif class="badge badge-yellow" title="Meta refused part of it: see the warnings panel after the fetch">
                                <x-icon name="alert-triangle" class="size-3.5" /> Done, with warnings
                            </span>
                            <span x-show="state({{ $acc->id }}) === 'running'" @if($initial['state'] !== 'running') x-cloak @endif class="badge badge-blue">
                                <span class="size-3 animate-spin rounded-full border-2 border-brand-200 border-t-brand-600 dark:border-brand-500/30 dark:border-t-brand-300"></span>
                                Fetching <span class="tabular-nums" x-text="`${progress({{ $acc->id }})}%`">{{ $initial['progress'] }}%</span>
                            </span>
                            <span x-show="state({{ $acc->id }}) === 'queued'" @if($initial['state'] !== 'queued') x-cloak @endif class="badge badge-gray">
                                <x-icon name="clock" class="size-3.5" /> Waiting
                            </span>
                            <span x-show="state({{ $acc->id }}) === 'failed'" @if($initial['state'] !== 'failed') x-cloak @endif class="badge badge-red">
                                <x-icon name="alert-circle" class="size-3.5" /> Failed
                            </span>
                            <span x-show="state({{ $acc->id }}) === 'stopped'" @if($initial['state'] !== 'stopped') x-cloak @endif class="badge badge-gray">
                                <x-icon name="player-stop" class="size-3.5" /> Stopped
                            </span>
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if(!$metaConfigured)
        <div class="alert alert-warning">
            <x-icon name="alert-triangle" class="size-5 shrink-0" />
            <div class="flex-1">
                <p class="font-semibold">{{ $currentCompany->name }} has no Meta app yet</p>
                <p class="mt-0.5 opacity-90">Each company connects through its own Meta App ID and App Secret.</p>
            </div>
            @can('company-edit')
                <a href="{{ route('companies.edit', $currentCompany->id) }}#meta-app" wire:navigate class="btn btn-secondary btn-sm self-center">Set up</a>
            @endcan
        </div>
    @endif

    @if($neverFetched && !$syncing)
        <div class="alert alert-info">
            <x-icon name="info-circle" class="size-5 shrink-0" />
            <div class="flex-1">
                <p class="font-semibold">Accounts linked, no data yet</p>
                <p class="mt-0.5 opacity-90">Pick the accounts and the period to load posts, insights, audience, messages and ads.</p>
            </div>
            @can('social-sync')
                <button type="button" wire:click="openFetch" class="btn btn-primary btn-sm self-center">Fetch now</button>
            @endcan
        </div>
    @endif

    @foreach($expiringAds as $acc)
        @php $expired = $acc->token_expires_at->isPast(); @endphp
        <div @class(['alert', 'alert-danger' => $expired, 'alert-warning' => !$expired])>
            <x-icon name="clock-exclamation" class="size-5 shrink-0" />
            <div class="flex-1">
                <p class="font-semibold">{{ $expired ? 'Ad account access expired' : 'Ad account access expires in ' . $acc->token_expires_at->diffForHumans(null, true) }}</p>
                <p class="mt-0.5 opacity-90">{{ $acc->display_name }}: Meta limits ad access to about 60 days. Connect again to renew it.</p>
            </div>
            @can('social-connect')
                @if($metaConfigured)
                    <a href="{{ route('social.connect', ['company' => $currentCompany->id]) }}" class="btn btn-secondary btn-sm self-center">Reconnect</a>
                @endif
            @endcan
        </div>
    @endforeach

    @if($warningAccounts->isNotEmpty())
        <details class="alert alert-warning group block">
            <summary class="flex cursor-pointer list-none items-center gap-3">
                <x-icon name="alert-triangle" class="size-5 shrink-0" />
                <span class="flex-1 font-semibold">
                    Meta refused part of the last fetch on {{ $warningAccounts->count() }} {{ str('account')->plural($warningAccounts->count()) }}
                </span>
                <span class="text-xs font-medium underline-offset-2 group-open:hidden hover:underline">Show details</span>
                <x-icon name="chevron-down" class="size-4 transition-transform group-open:rotate-180" />
            </summary>
            <div class="mt-3 space-y-3 pl-8">
                @foreach($warningAccounts as $acc)
                    <div>
                        <p class="font-medium">{{ $acc->display_name }}</p>
                        <ul class="mt-1 list-disc space-y-0.5 pl-5 text-xs opacity-90">
                            @foreach(array_filter(explode("\n", $acc->last_sync_error)) as $line)
                                <li class="break-words">{{ $line }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
                <p class="text-xs opacity-80">Usually a missing permission on the Meta app, or a metric Meta has retired. The rest of the data was still fetched.</p>
            </div>
        </details>
    @endif
</div>
