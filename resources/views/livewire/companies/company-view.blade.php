<div>
    <x-ui.page-header title="Companies" :subtitle="$companies->count() . ' ' . str('company')->plural($companies->count())">
        <x-slot:actions>
            @if(auth()->user()->can('company-create') && auth()->user()->canAccessAllCompanies())
                <a href="{{ route('companies.create') }}" wire:navigate class="btn btn-primary"><x-icon name="plus" class="size-4" /> Add company</a>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="card overflow-hidden" x-data="tableSearch">
        @if($companies->isEmpty())
            <x-ui.empty icon="building" title="No companies yet">Companies you add appear here.</x-ui.empty>
        @else
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 p-4 dark:border-slate-800">
                <div class="relative w-full sm:max-w-xs">
                    <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                    <input type="search" x-model="q" class="input pl-9" placeholder="Search companies" aria-label="Search companies">
                </div>
                <button type="button" class="btn btn-secondary btn-sm" x-on:click="exportTable($refs.table, 'companies')">
                    <x-icon name="download" class="size-4" /> Export CSV
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="table" x-ref="table">
                    <thead>
                        <tr>
                            <th>Company</th>
                            <th>Phone</th>
                            <th class="!text-right">Branches</th>
                            <th class="!text-right">Users</th>
                            <th class="!text-right">Social accounts</th>
                            <th>Status</th>
                            <th data-no-export><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($companies as $company)
                            <tr data-row x-show="matches($el)" wire:key="company-{{ $company->id }}">
                                <td>
                                    <div class="flex items-center gap-3">
                                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-xs font-semibold text-brand-700 dark:bg-brand-500/10 dark:text-brand-300">
                                            {{ strtoupper(mb_substr($company->name, 0, 2)) }}
                                        </span>
                                        <div class="min-w-0">
                                            <p class="truncate font-medium text-slate-900 dark:text-white">{{ $company->name }}</p>
                                            <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $company->email ?: 'No e-mail' }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap">{{ $company->phone ?: '—' }}</td>
                                <td class="text-right tabular-nums">{{ $company->branches_count }}</td>
                                <td class="text-right tabular-nums">{{ $company->users_count }}</td>
                                <td class="text-right tabular-nums">{{ $company->social_accounts_count }}</td>
                                <td>
                                    <span class="badge {{ $company->is_active ? 'badge-green' : 'badge-gray' }}">{{ $company->is_active ? 'Active' : 'Inactive' }}</span>
                                </td>
                                <td data-no-export>
                                    <div class="flex justify-end gap-1">
                                        @can('social-list')
                                            <a href="{{ route('social', ['company' => $company->id]) }}" wire:navigate class="btn btn-ghost btn-icon btn-sm" title="Social Media" aria-label="Open {{ $company->name }} in Social Media">
                                                <x-icon name="brand-instagram" class="size-4" />
                                            </a>
                                        @endcan
                                        @can('company-edit')
                                            <a href="{{ route('companies.edit', $company->id) }}" wire:navigate class="btn btn-ghost btn-icon btn-sm" title="Edit" aria-label="Edit {{ $company->name }}">
                                                <x-icon name="pencil" class="size-4" />
                                            </a>
                                        @endcan
                                        @can('company-delete')
                                            <button type="button" class="btn btn-ghost btn-icon btn-sm text-red-600 hover:bg-red-50 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-500/10"
                                                title="Delete" aria-label="Delete {{ $company->name }}"
                                                x-on:click="$dispatch('confirm', {
                                                    title: @js('Delete ' . $company->name . '?'),
                                                    text: 'The company disappears from the app, along with access to its pages for its users.',
                                                    confirm: 'Delete company',
                                                    onConfirm: () => $wire.delete({{ $company->id }})
                                                })">
                                                <x-icon name="trash" class="size-4" />
                                            </button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        <tr x-show="q && !anyMatch" x-cloak>
                            <td colspan="7" class="py-10 text-center text-sm text-slate-500 dark:text-slate-400">No companies match “<span x-text="q"></span>”.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
