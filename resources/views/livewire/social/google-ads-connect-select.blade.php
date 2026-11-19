<div class="mx-auto max-w-3xl">
    <x-ui.page-header :title="'Choose Google Ads accounts for ' . $company->name"
        subtitle="Pick the accounts this company should read. Client accounts of a manager account are listed under it." />

    <div class="space-y-6">
        @php $groups = collect($accounts)->groupBy(fn ($a) => $a['manager_name'] ?? ''); @endphp

        @forelse($groups as $manager => $list)
            <section class="card overflow-hidden" wire:key="group-{{ md5($manager) }}">
                <div class="card-header">
                    <h2 class="card-title flex items-center gap-2">
                        <x-icon name="brand-google" class="size-5 text-[#34A853]" />
                        @if($manager !== '')
                            {{ $manager }} <span class="text-sm font-normal text-slate-400">(manager account)</span>
                        @else
                            Accounts you can open directly
                        @endif
                    </h2>
                    @if($list->count() > 1)
                        <button type="button" class="link text-xs"
                            wire:click="$set('selected', @js(collect($selected)->merge($list->pluck('id'))->unique()->values()->all()))">Select all</button>
                    @endif
                </div>
                <div class="max-h-[28rem] divide-y divide-slate-100 overflow-y-auto scrollbar-thin dark:divide-slate-800">
                    @foreach($list as $acc)
                        <label class="flex cursor-pointer items-center gap-4 px-5 py-4 transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/40" wire:key="gads-{{ $acc['id'] }}">
                            <input type="checkbox" class="checkbox" value="{{ $acc['id'] }}" wire:model="selected">
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium text-slate-900 dark:text-white">{{ $acc['name'] }}</span>
                                <span class="block text-xs text-slate-500 dark:text-slate-400">
                                    {{ \App\Services\GoogleAds\GoogleAdsClient::formatCustomerId($acc['id']) }}{{ $acc['currency'] ? ' · ' . $acc['currency'] : '' }}
                                </span>
                            </span>
                            @if($acc['test'])
                                <span class="badge badge-blue">Test account</span>
                            @endif
                            @unless($acc['active'])
                                <span class="badge badge-gray">Inactive</span>
                            @endunless
                        </label>
                    @endforeach
                </div>
            </section>
        @empty
            <div class="card">
                <x-ui.empty icon="brand-google" title="No Google Ads accounts found">
                    The Google account you signed in with can't open any Google Ads account. Sign in with one that has access, or ask the account's admin to add it.
                </x-ui.empty>
            </div>
        @endforelse

        @if($skipped)
            <details class="alert alert-warning group block">
                <summary class="flex cursor-pointer list-none items-center gap-3">
                    <x-icon name="alert-triangle" class="size-5 shrink-0" />
                    <span class="flex-1 font-semibold">Google refused {{ count($skipped) }} {{ str('account')->plural(count($skipped)) }}</span>
                    <x-icon name="chevron-down" class="size-4 transition-transform group-open:rotate-180" />
                </summary>
                <ul class="mt-2 list-disc space-y-0.5 pl-12 text-xs opacity-90">
                    @foreach($skipped as $line)
                        <li class="break-words">{{ $line }}</li>
                    @endforeach
                </ul>
            </details>
        @endif

        @error('selected')
            <div class="alert alert-danger" role="alert"><x-icon name="alert-circle" class="size-5 shrink-0" /> {{ $message }}</div>
        @enderror

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <button type="button" wire:click="cancel" class="btn btn-secondary">Cancel</button>
            <button type="button" wire:click="save" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save" @disabled(empty($accounts))>
                <span wire:loading wire:target="save" class="size-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                Link selected accounts
            </button>
        </div>
    </div>
</div>
