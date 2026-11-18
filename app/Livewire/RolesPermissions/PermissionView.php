<?php

namespace App\Livewire\RolesPermissions;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Str;
use Livewire\Component;
use Spatie\Permission\Models\Permission;

/**
 * Read-only list of every permission, grouped by module, with the roles that have it.
 */
class PermissionView extends Component
{
    use AuthorizesRequests;

    public function mount()
    {
        $this->authorize('permission-list');
    }

    public function render()
    {
        $groups = Permission::with('roles:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn ($p) => [
                'name' => $p->name,
                'module' => Str::headline(Str::before($p->name, '-')),
                'action' => Str::contains($p->name, '-') ? Str::headline(Str::after($p->name, '-')) : '—',
                'roles' => $p->roles->pluck('name')->sort()->values(),
            ])
            ->groupBy('module');

        return view('livewire.roles-permissions.permission-view', [
            'groups' => $groups,
            'total' => $groups->flatten(1)->count(),
        ]);
    }
}
