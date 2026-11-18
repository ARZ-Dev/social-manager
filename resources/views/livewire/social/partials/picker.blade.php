<x-ui.page-header title="Social Media" subtitle="Choose a company to see its Facebook, Instagram and ad accounts." />

<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <div class="relative w-full sm:max-w-sm">
        <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
        <input type="search" wire:model.live.debounce.300ms="companySearch" class="input pl-9" placeholder="Search companies" aria-label="Search companies" autofocus>
    </div>
    <p class="text-sm text-slate-500 dark:text-slate-400">{{ $pickerCompanies->count() }} of {{ $companyTotal }} {{ str('company')->plural($companyTotal) }}</p>
</div>

<div class="grid gap-4 transition-opacity sm:grid-cols-2 xl:grid-cols-3" wire:loading.class="opacity-50" wire:target="companySearch,selectCompany">
    @forelse($pickerCompanies as $c)
        @php $last = $c->social_accounts_max_last_synced_at ? \Illuminate\Support\Carbon::parse($c->social_accounts_max_last_synced_at) : null; @endphp
        <button type="button" wire:click="selectCompany({{ $c->id }})" wire:key="pick-{{ $c->id }}"
            class="card group flex items-start gap-4 p-5 text-left transition hover:-translate-y-0.5 hover:border-brand-300 hover:shadow-md dark:hover:border-brand-500/40">
            <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-sm font-semibold text-brand-700 dark:bg-brand-500/10 dark:text-brand-300">
                {{ strtoupper(mb_substr($c->name, 0, 2)) }}
            </span>
            <span class="min-w-0 flex-1">
                <span class="block truncate font-semibold text-slate-900 dark:text-white">{{ $c->name }}</span>
                <span class="mt-1.5 flex flex-wrap gap-1.5">
                    @if($c->social_accounts_count)
                        <span class="badge badge-green">{{ $c->social_accounts_count }} {{ str('account')->plural($c->social_accounts_count) }} linked</span>
                    @elseif(!$c->hasMetaApp())
                        <span class="badge badge-yellow">Meta app not set up</span>
                    @else
                        <span class="badge badge-gray">Not connected</span>
                    @endif
                    @unless($c->is_active)
                        <span class="badge badge-gray">Inactive</span>
                    @endunless
                </span>
                <span class="mt-3 block text-xs text-slate-500 dark:text-slate-400">
                    {{ $last ? 'Last fetched ' . $last->diffForHumans() : ($c->social_accounts_count ? 'Never fetched' : 'No data yet') }}
                </span>
            </span>
            <x-icon name="chevron-right" class="size-5 shrink-0 text-slate-300 transition-colors group-hover:text-brand-500 dark:text-slate-600" />
        </button>
    @empty
        <div class="card sm:col-span-2 xl:col-span-3">
            <x-ui.empty icon="building" :title="$companySearch ? 'No companies match “' . $companySearch . '”' : 'No companies yet'">
                @if(!$companySearch) Add a company first, then connect its social accounts here. @endif
                @if(!$companySearch && auth()->user()->can('company-create'))
                    <x-slot:action><a href="{{ route('companies.create') }}" wire:navigate class="btn btn-primary">Add company</a></x-slot:action>
                @endif
            </x-ui.empty>
        </div>
    @endforelse
</div>
