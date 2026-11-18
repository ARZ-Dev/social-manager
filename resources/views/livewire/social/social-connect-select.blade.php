<div class="mx-auto max-w-3xl">
    <x-ui.page-header :title="'Choose accounts for ' . $company->name"
        subtitle="Pick the Facebook Pages (their Instagram accounts come along) and ad accounts this company should read." />

    <div class="space-y-6">
        <section class="card overflow-hidden">
            <div class="card-header">
                <h2 class="card-title flex items-center gap-2"><x-icon name="brand-facebook" class="size-5 text-[#1877F2]" /> Facebook Pages &amp; Instagram</h2>
                @if(count($pages) > 1)
                    <button type="button" class="link text-xs" wire:click="$set('selectedPages', @js(collect($pages)->pluck('id')->map(fn ($id) => (string) $id)->all()))">Select all</button>
                @endif
            </div>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($pages as $page)
                    <label class="flex cursor-pointer items-center gap-4 px-5 py-4 transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/40" wire:key="page-{{ $page['id'] }}">
                        <input type="checkbox" class="checkbox" value="{{ $page['id'] }}" wire:model="selectedPages">
                        <span class="relative flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-slate-100 text-xs font-semibold text-slate-500 dark:bg-slate-800">
                            {{ strtoupper(mb_substr($page['name'], 0, 2)) }}
                            @if($page['picture'])
                                <img src="{{ $page['picture'] }}" alt="" class="absolute inset-0 size-full object-cover" onerror="this.remove()">
                            @endif
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium text-slate-900 dark:text-white">{{ $page['name'] }}</span>
                            @if($page['instagram'])
                                <span class="mt-0.5 flex items-center gap-1 text-xs text-slate-500 dark:text-slate-400">
                                    <x-icon name="brand-instagram" class="size-3.5 text-[#E1306C]" /> {{ '@' . $page['instagram']['username'] }} is linked automatically
                                </span>
                            @else
                                <span class="mt-0.5 block text-xs text-slate-400">No Instagram professional account linked to this Page</span>
                            @endif
                        </span>
                    </label>
                @empty
                    <x-ui.empty icon="brand-facebook" title="No Pages found" compact>
                        Make sure you're an admin of at least one Facebook Page and that you shared it when Facebook asked.
                    </x-ui.empty>
                @endforelse
            </div>
        </section>

        <section class="card overflow-hidden">
            <div class="card-header">
                <h2 class="card-title flex items-center gap-2"><x-icon name="speakerphone" class="size-5 text-amber-500" /> Ad accounts <span class="text-sm font-normal text-slate-400">(for ads and boosts)</span></h2>
            </div>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($adAccounts as $ad)
                    <label class="flex cursor-pointer items-center gap-4 px-5 py-4 transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/40" wire:key="ad-{{ $ad['id'] }}">
                        <input type="checkbox" class="checkbox" value="{{ $ad['id'] }}" wire:model="selectedAds">
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium text-slate-900 dark:text-white">{{ $ad['name'] }}</span>
                            <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $ad['id'] }}{{ $ad['currency'] ? ' · ' . $ad['currency'] : '' }}</span>
                        </span>
                        @unless($ad['active'])
                            <span class="badge badge-gray">Inactive</span>
                        @endunless
                    </label>
                @empty
                    <x-ui.empty icon="speakerphone" title="No ad accounts found" compact>That's fine if the company doesn't run ads.</x-ui.empty>
                @endforelse
            </div>
        </section>

        @error('selection')
            <div class="alert alert-danger" role="alert"><x-icon name="alert-circle" class="size-5 shrink-0" /> {{ $message }}</div>
        @enderror

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <button type="button" wire:click="cancel" class="btn btn-secondary">Cancel</button>
            <button type="button" wire:click="save" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading wire:target="save" class="size-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                Link selected accounts
            </button>
        </div>
    </div>
</div>
