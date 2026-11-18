<div>
    <x-ui.page-header title="Users" :subtitle="$users->count() . ' ' . str('user')->plural($users->count())">
        <x-slot:actions>
            @can('user-create')
                <a href="{{ route('users.create') }}" wire:navigate class="btn btn-primary"><x-icon name="user-plus" class="size-4" /> Add user</a>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <div class="card overflow-hidden" x-data="tableSearch">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 p-4 dark:border-slate-800">
            <div class="relative w-full sm:max-w-xs">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                <input type="search" x-model="q" class="input pl-9" placeholder="Search name, role, company…" aria-label="Search users">
            </div>
            <button type="button" class="btn btn-secondary btn-sm" x-on:click="exportTable($refs.table, 'users')">
                <x-icon name="download" class="size-4" /> Export CSV
            </button>
        </div>
        <div class="overflow-x-auto">
            <table class="table" x-ref="table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Role</th>
                        <th>Company</th>
                        <th>E-mail</th>
                        <th>Phone</th>
                        <th data-no-export><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                        @php $role = $user->roles->first()?->name; @endphp
                        <tr data-row x-show="matches($el)" wire:key="user-{{ $user->id }}">
                            <td>
                                <div class="flex items-center gap-3">
                                    <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                        {{ strtoupper(mb_substr($user->first_name ?: $user->username, 0, 1) . mb_substr($user->last_name ?? '', 0, 1)) }}
                                    </span>
                                    <div class="min-w-0">
                                        <p class="truncate font-medium text-slate-900 dark:text-white">{{ trim($user->first_name . ' ' . $user->last_name) ?: $user->username }}</p>
                                        <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ '@' . $user->username }}</p>
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if($role)
                                    <span class="badge {{ $role === 'Super Admin' ? 'badge-red' : 'badge-blue' }}">{{ $role }}</span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td>{{ $user->company->name ?? '—' }}</td>
                            <td class="max-w-[14rem] truncate">{{ $user->email ?: '—' }}</td>
                            <td class="whitespace-nowrap">{{ $user->phone ?: '—' }}</td>
                            <td data-no-export>
                                <div class="flex justify-end gap-1">
                                    @can('user-edit')
                                        <a href="{{ route('users.edit', $user->id) }}" wire:navigate class="btn btn-ghost btn-icon btn-sm" title="Edit" aria-label="Edit {{ $user->username }}">
                                            <x-icon name="pencil" class="size-4" />
                                        </a>
                                    @endcan
                                    @if($user->id != 1)
                                        @can('user-delete')
                                            <button type="button" class="btn btn-ghost btn-icon btn-sm text-red-600 hover:bg-red-50 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-500/10"
                                                title="Delete" aria-label="Delete {{ $user->username }}"
                                                x-on:click="$dispatch('confirm', {
                                                    title: @js('Delete ' . (trim($user->first_name . ' ' . $user->last_name) ?: $user->username) . '?'),
                                                    text: 'They will no longer be able to sign in.',
                                                    confirm: 'Delete user',
                                                    onConfirm: () => $wire.delete({{ $user->id }})
                                                })">
                                                <x-icon name="trash" class="size-4" />
                                            </button>
                                        @endcan
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-ui.empty icon="users" title="No users yet" compact /></td></tr>
                    @endforelse
                    <tr x-show="q && !anyMatch" x-cloak>
                        <td colspan="6" class="py-10 text-center text-sm text-slate-500 dark:text-slate-400">No users match “<span x-text="q"></span>”.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
