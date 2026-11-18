<?php

namespace App\Livewire\RolesPermissions;

use App\Models\Company;
use App\Utils\Constants;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use App\Models\Role;

class RoleView extends Component
{
    use AuthorizesRequests;

    public $role;

    public $permissions;
    public $filteredPermissions;
    public $selectedPermissions = [];

    public bool $editing = false;
    public bool $isAllPermissionsSelected = false;
    public bool $allowPermissionEditing = true;
    public bool $showModal = false;

    public $name = "";
    public $company_id = '';
    public $companies = [];

    public array $primaryRolesIds;

    protected $listeners = [
        'delete',
    ];

    public function mount()
    {
        $this->authorize('role-list');
        // Only Super Admins pick a company; everyone else gets their own.
        if (auth()->user()->canAccessAllCompanies()) {
            $this->companies = Company::orderBy('name')->get();
        }
        $this->permissions = Permission::all();
        foreach ($this->permissions as $permission) {
            $prefix = explode('-', $permission->name)[0];
            if (count(explode('-', $permission->name)) > 1) {
                $this->filteredPermissions[$prefix][] = [
                    'id' => $permission->id,
                    'name' => explode('-', $permission->name)[1],
                ];
            }
        }
        

        $this->primaryRolesIds = [Constants::SUPER_ADMIN_ROLE_ID, Constants::PROCUREMENT_MANAGER_ROLE_ID, Constants::SALES_ROLE_ID];
    }

    public function clearData()
    {
        $this->reset('editing', 'name', 'company_id', 'selectedPermissions', 'role', 'isAllPermissionsSelected', 'allowPermissionEditing');
        $this->resetValidation();
    }

    public function create()
    {
        $this->authorize('role-create');
        $this->clearData();
        $this->showModal = true;
    }

    public function closeModal()
    {
        $this->showModal = false;
    }

    /** Tick or untick every action of one module (e.g. all "user-*" permissions). */
    public function toggleGroup($group)
    {
        $names = collect($this->filteredPermissions[$group] ?? [])->map(fn ($p) => $group . '-' . $p['name']);
        $allOn = $names->every(fn ($n) => in_array($n, $this->selectedPermissions));
        $this->selectedPermissions = $allOn
            ? array_values(array_diff($this->selectedPermissions, $names->all()))
            : array_values(array_unique(array_merge($this->selectedPermissions, $names->all())));
        $this->isAllPermissionsSelected = count($this->selectedPermissions) == count($this->permissions);
    }

    public function togglePermission($permission)
    {
        if (in_array($permission, $this->selectedPermissions)) {
            $this->selectedPermissions = array_diff($this->selectedPermissions, [$permission]);
        } else {
            $this->selectedPermissions[] = $permission;
        }
        $this->isAllPermissionsSelected = count($this->selectedPermissions) == count($this->permissions);
    }

    public function selectAllPermissions()
    {
        $this->reset('selectedPermissions');
        if (!$this->isAllPermissionsSelected) {
            foreach ($this->permissions as $permission) {
                $this->selectedPermissions[] = $permission->name;
            }
        }
        $this->isAllPermissionsSelected = !$this->isAllPermissionsSelected;
    }

    public function edit($id)
    {
        $this->clearData();
        $this->editing = true;
        $this->allowPermissionEditing = $id != Constants::SUPER_ADMIN_ROLE_ID;
        $this->role = Role::visibleTo(auth()->user())->findOrFail($id);
        $this->name = $this->role->name;
        $this->company_id = $this->role->company_id ?? '';
        $this->selectedPermissions = $this->role->permissions()->pluck('name')->toArray();
        $this->isAllPermissionsSelected = count($this->selectedPermissions) == count($this->permissions);
        $this->showModal = true;
    }

    public function rules()
    {
        return [
            'name' => ['required', 'max:255', Rule::unique('roles', 'name')->ignore($this->role?->id)],
            'company_id' => ['nullable', Rule::exists('companies', 'id')->whereNull('deleted_at')],
        ];
    }

    public function store()
    {

        $this->validate();

        $viewer = auth()->user();
        // Super Admins choose the company (empty = global role); others always use their own.
        $companyId = $viewer->canAccessAllCompanies()
            ? ($this->company_id ?: null)
            : $viewer->company_id;

        if ($this->role) {
            $this->authorize('role-edit');
            $this->role = Role::visibleTo($viewer)->findOrFail($this->role->id);
            $this->role->update([
                'name' => $this->name,
                'company_id' => $companyId,
            ]);

        } else {
            $this->authorize('role-create');
            $this->role = Role::create([
                'name' => $this->name,
                'company_id' => $companyId,
            ]);
        }
        $this->role->syncPermissions($this->selectedPermissions);

        $this->showModal = false;

        $this->dispatch('swal:success', [
            'title' => 'Great!',
            'text'  => "Role has been saved successfully!",
        ]);
    }

    public function delete($id)
    {
        $this->authorize('role-delete');
        // check if role has users before deleting
        $role = Role::visibleTo(auth()->user())->findOrFail($id);
        if (count($role->users)) {
            $this->dispatch('swal:error', [
                'title' => 'Error!',
                'text'  => "You have to unassign this role from users before deleting!",
            ]);
            return;
        }

        $role->delete();

        $this->dispatch('swal:success', [
            'title' => 'Great!',
            'text'  => "Role has been deleted successfully!",
        ]);
    }

    public function render()
    {
        $data = [];
        $data['roles'] = Role::visibleTo(auth()->user())->with('company')->withCount(['users', 'permissions'])->orderBy('name')->get();

        return view('livewire.roles-permissions.role-view', $data);
    }
}
