@php $superAdminId = \App\Utils\Constants::SUPER_ADMIN_ROLE_ID; @endphp

<div>
    <x-ui.page-header title="Roles" subtitle="What each group of users is allowed to do.">
        <x-slot:actions>
            @can('role-create')
                <button type="button" wire:click="create" class="btn btn-primary"><x-icon name="plus" class="size-4" /> Add role</button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <div class="card overflow-hidden">
        @if($roles->isEmpty())
            <x-ui.empty icon="shield-lock" title="No roles yet" />
        @else
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Role</th>
                            <th>Company</th>
                            <th class="!text-right">Permissions</th>
                            <th class="!text-right">Users</th>
                            <th><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($roles as $role)
                            <tr wire:key="role-{{ $role->id }}">
                                <td>
                                    <div class="flex items-center gap-3">
                                        <span @class([
                                            'flex size-9 shrink-0 items-center justify-center rounded-lg',
                                            'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400' => $role->id == $superAdminId,
                                            'bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-300' => $role->id != $superAdminId,
                                        ])>
                                            <x-icon name="shield-lock" class="size-4" />
                                        </span>
                                        <span class="font-medium text-slate-900 dark:text-white">{{ $role->name }}</span>
                                    </div>
                                </td>
                                <td>
                                    @if($role->company)
                                        {{ $role->company->name }}
                                    @else
                                        <span class="badge badge-gray">All companies</span>
                                    @endif
                                </td>
                                <td class="text-right tabular-nums">{{ $role->id == $superAdminId ? 'All' : $role->permissions_count }}</td>
                                <td class="text-right tabular-nums">{{ $role->users_count }}</td>
                                <td>
                                    <div class="flex justify-end gap-1">
                                        @if($role->id != $superAdminId)
                                            @can('role-edit')
                                                <button type="button" wire:click="edit({{ $role->id }})" class="btn btn-ghost btn-icon btn-sm" title="Edit" aria-label="Edit {{ $role->name }}">
                                                    <x-icon name="pencil" class="size-4" />
                                                </button>
                                            @endcan
                                            @can('role-delete')
                                                <button type="button" class="btn btn-ghost btn-icon btn-sm text-red-600 hover:bg-red-50 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-500/10"
                                                    title="Delete" aria-label="Delete {{ $role->name }}"
                                                    x-on:click="$dispatch('confirm', {
                                                        title: @js('Delete the ' . $role->name . ' role?'),
                                                        text: {{ $role->users_count ? "'Remove it from its " . $role->users_count . " user(s) first.'" : "'This cannot be undone.'" }},
                                                        confirm: 'Delete role',
                                                        onConfirm: () => $wire.delete({{ $role->id }})
                                                    })">
                                                    <x-icon name="trash" class="size-4" />
                                                </button>
                                            @endcan
                                        @else
                                            <span class="text-xs text-slate-400">Built-in</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Create / edit role --}}
    @if($showModal)
        <x-ui.modal close="closeModal" :title="$editing ? 'Edit role' : 'Add role'" size="3xl">
            <form id="role-form" wire:submit="store" class="space-y-5" novalidate>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div @class(['sm:col-span-2' => !auth()->user()->canAccessAllCompanies()])>
                        <label class="label" for="modalRoleName">Name</label>
                        <input wire:model="name" type="text" id="modalRoleName" @class(['input', 'input-error' => $errors->has('name')]) placeholder="e.g. Social media manager" autofocus>
                        @error('name') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    @if(auth()->user()->canAccessAllCompanies())
                        <div>
                            <label class="label" for="modalRoleCompany">Company</label>
                            <select wire:model="company_id" id="modalRoleCompany" @class(['select', 'input-error' => $errors->has('company_id')])>
                                <option value="">All companies (global role)</option>
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}">{{ $company->name }}</option>
                                @endforeach
                            </select>
                            @error('company_id') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                    @endif
                </div>

                <div>
                    <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                        <h3 class="text-sm font-semibold">Permissions</h3>
                        <label class="flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-300">
                            <input type="checkbox" class="checkbox" wire:click="selectAllPermissions" @checked($isAllPermissionsSelected) @disabled(!$allowPermissionEditing)>
                            Full access (all permissions)
                        </label>
                    </div>
                    <div class="divide-y divide-slate-100 overflow-hidden rounded-lg border border-slate-200 dark:divide-slate-800 dark:border-slate-800">
                        @foreach($filteredPermissions ?? [] as $group => $permissions)
                            @php
                                $names = collect($permissions)->map(fn ($p) => $group . '-' . $p['name']);
                                $allOn = $names->every(fn ($n) => in_array($n, $selectedPermissions));
                            @endphp
                            <div class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center" wire:key="perm-group-{{ $group }}">
                                <div class="flex w-48 shrink-0 items-center gap-2">
                                    <input type="checkbox" class="checkbox" id="group-{{ $group }}" wire:click="toggleGroup('{{ $group }}')" @checked($allOn) @disabled(!$allowPermissionEditing)>
                                    <label for="group-{{ $group }}" class="text-sm font-medium text-slate-900 dark:text-white">{{ ucfirst(str_replace('_', ' ', $group)) }}</label>
                                </div>
                                <div class="flex flex-wrap gap-x-5 gap-y-2">
                                    @foreach($permissions as $permission)
                                        @php $key = $group . '-' . $permission['name']; @endphp
                                        <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400">
                                            <input type="checkbox" class="checkbox" wire:click="togglePermission('{{ $key }}')" @checked(in_array($key, $selectedPermissions)) @disabled(!$allowPermissionEditing)>
                                            {{ ucfirst($permission['name']) }}
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </form>

            <x-slot:footer>
                <button type="button" class="btn btn-secondary" x-on:click="close()">Cancel</button>
                <button type="submit" form="role-form" class="btn btn-primary" wire:loading.attr="disabled" wire:target="store">
                    <span wire:loading wire:target="store" class="size-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                    {{ $editing ? 'Save role' : 'Create role' }}
                </button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
