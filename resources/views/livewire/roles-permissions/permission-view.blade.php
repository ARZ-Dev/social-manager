<div>
    <x-ui.page-header title="Permissions" :subtitle="$total . ' permissions in ' . $groups->count() . ' modules. Assign them to roles on the Roles page.'">
        <x-slot:actions>
            @can('role-list')
                <a href="{{ route('roles') }}" wire:navigate class="btn btn-secondary"><x-icon name="shield-lock" class="size-4" /> Manage roles</a>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <div class="card overflow-hidden" x-data="tableSearch">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 p-4 dark:border-slate-800">
            <div class="relative w-full sm:max-w-xs">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                <input type="search" x-model="q" class="input pl-9" placeholder="Search module, action or role" aria-label="Search permissions">
            </div>
            <button type="button" class="btn btn-secondary btn-sm" x-on:click="exportTable($refs.table, 'permissions')">
                <x-icon name="download" class="size-4" /> Export CSV
            </button>
        </div>
        <div class="overflow-x-auto">
            <table class="table" x-ref="table">
                <thead>
                    <tr>
                        <th>Module</th>
                        <th>Action</th>
                        <th>Key</th>
                        <th>Roles with it</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($groups as $module => $permissions)
                        @foreach($permissions as $p)
                            <tr data-row x-show="matches($el)" wire:key="perm-{{ $p['name'] }}">
                                <td class="font-medium text-slate-900 dark:text-white">{{ $module }}</td>
                                <td>{{ $p['action'] }}</td>
                                <td><code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ $p['name'] }}</code></td>
                                <td>
                                    <div class="flex flex-wrap gap-1">
                                        @forelse($p['roles'] as $role)
                                            <span class="badge {{ $role === 'Super Admin' ? 'badge-red' : 'badge-blue' }}">{{ $role }}</span>
                                        @empty
                                            <span class="text-xs text-slate-400">No role</span>
                                        @endforelse
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    @empty
                        <tr><td colspan="4"><x-ui.empty icon="key" title="No permissions defined" compact /></td></tr>
                    @endforelse
                    <tr x-show="q && !anyMatch" x-cloak>
                        <td colspan="4" class="py-10 text-center text-sm text-slate-500 dark:text-slate-400">Nothing matches “<span x-text="q"></span>”.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
