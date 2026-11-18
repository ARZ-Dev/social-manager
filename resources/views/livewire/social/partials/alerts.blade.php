@php $hasAlerts = !$metaConfigured || $neverFetched || $expiringAds->isNotEmpty() || $warningAccounts->isNotEmpty(); @endphp

<div @class(['space-y-3', 'mb-6' => $hasAlerts]) wire:loading.class="mb-6" wire:target="fetch">
    {{-- While fetching --}}
    <div wire:loading.flex wire:target="fetch" class="alert alert-info items-center">
        <span class="size-5 shrink-0 animate-spin rounded-full border-2 border-brand-200 border-t-brand-600 dark:border-brand-500/30 dark:border-t-brand-300"></span>
        <div>
            <p class="font-semibold">Fetching data from Meta…</p>
            <p class="opacity-80">This can take a few minutes for busy accounts. Keep this page open.</p>
        </div>
    </div>

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

    @if($neverFetched)
        <div class="alert alert-info" wire:loading.remove wire:target="fetch">
            <x-icon name="info-circle" class="size-5 shrink-0" />
            <div class="flex-1">
                <p class="font-semibold">Accounts linked, no data yet</p>
                <p class="mt-0.5 opacity-90">Fetch to load the last {{ config('meta.history_days') }} days of posts, insights, audience, messages and ads.</p>
            </div>
            @can('social-sync')
                <button type="button" wire:click="fetch" class="btn btn-primary btn-sm self-center">Fetch now</button>
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
