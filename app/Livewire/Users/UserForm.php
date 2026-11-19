<?php

namespace App\Livewire\Users;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Component;
use App\Models\Role;

class UserForm extends Component
{
    use AuthorizesRequests;

    public $roles;
    public $users;
    
    public  $user;
    
    public string $first_name = "";
    public string $last_name = "";
    public string $username = "";
    public string $email = "";
    public $role_name;
    public $company_id = '';
    public $companies;

    public string $phone = "";
    public string $password = "";
    public string $password_confirmation = "";

    public bool $editing = false;
    public int $status;


    protected $listeners = ['store', 'update'];

    public function mount($id = 0, $status = 0)
    {
        $this->authorize('user-list');
        $viewer = auth()->user();
        // Company users only see their company's roles; Super Admins see all.
        $this->roles = Role::visibleTo($viewer)->with('company')->orderBy('name')->get();
        $this->companies = Company::visibleTo($viewer)->where('is_active', true)->orderBy('name')->get();

        if (!$viewer->canAccessAllCompanies()) {
            $this->company_id = $viewer->company_id ?? '';
        }

        $this->status=$status;

        $this->users = User::visibleTo($viewer)->get();

        if ($id) {

            $this->editing = true;
            $this->user = User::visibleTo($viewer)->findOrFail($id);

            $this->first_name = $this->user->first_name;
            $this->last_name = $this->user->last_name;
            $this->username = $this->user->username;
            $this->email = $this->user->email ?? '';

            $this->phone = $this->user->phone;
            $this->company_id = $this->user->company_id ?? '';

            // Keep an inactive company selectable if the user already belongs to it.
            if ($this->user->company && !$this->companies->contains('id', $this->user->company_id)) {
                $this->companies->push($this->user->company);
            }

            $this->role_name = $this->user->roles[0]->name ?? "";

            $this->users = User::visibleTo($viewer)->where('id', '<>', $id)->get();

        }
    }

    /**
     * Roles offered for the selected company: that company's roles, plus
     * global roles (only visible to Super Admins anyway).
     */
    public function getAvailableRolesProperty()
    {
        return $this->roles
            ->filter(fn ($role) => $role->company_id === null || $role->company_id == $this->company_id)
            ->values();
    }

    /**
     * Company changed: drop a role that doesn't belong to the new company.
     */
    public function updatedCompanyId()
    {
        if (!$this->availableRoles->contains('name', $this->role_name)) {
            $this->role_name = '';
        }
    }

    public function rules()
    {
        return [

            'first_name' => ['required', 'string'],
            'last_name' => ['required', 'string'],
            'username' => ['required', Rule::unique('users', 'username')],
            'email' => $this->emailRule(),
            'role_name' => $this->roleRule(),
            'company_id' => $this->companyRule(),

            'phone' => ['required'],
            'password' => ['required', 'confirmed']
        ];
    }

    /**
     * Every user belongs to one company, except Super Admins who see all companies.
     */
    protected function companyRule(): array
    {
        $viewer = auth()->user();

        if (!$viewer->canAccessAllCompanies()) {
            // Company-scoped users can only assign their own company.
            return ['required', Rule::in([$viewer->company_id])];
        }

        return [
            Rule::requiredIf(fn () => $this->role_name !== 'Super Admin'),
            'nullable',
            Rule::exists('companies', 'id')->whereNull('deleted_at'),
        ];
    }

    /**
     * users.email has a unique index: check it here instead of letting the
     * insert fail. Blank emails are saved as NULL (see normalizeEmail), which
     * the index allows any number of times.
     */
    protected function emailRule(): array
    {
        return ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user?->id)];
    }

    /** Livewire skips the TrimStrings / ConvertEmptyStringsToNull middleware. */
    protected function normalizeEmail(): void
    {
        $this->email = trim($this->email);
    }

    protected function roleRule(): array
    {
        return [
            'required',
            Rule::in($this->roles->pluck('name')->all()),
            // A company role can only go to a user of that same company.
            function ($attribute, $value, $fail) {
                $role = $this->roles->firstWhere('name', $value);
                if ($role && $role->company_id && $role->company_id != $this->company_id) {
                    $fail('The selected role belongs to a different company.');
                }
            },
        ];
    }

    public function store()
    {

        $this->authorize('user-create');
        $this->normalizeEmail();
        $this->validate();

        $user = User::create([
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'username' => $this->username,
            'email' => $this->email ?: null,
            'phone' => $this->phone,
            'company_id' => $this->company_id ?: null,
            'password' => Hash::make($this->password),

        ]);


        $user->assignRole($this->role_name);

        return to_route('users')->with('success', 'User has been created successfully!');
    }

    public function update()
    {

        
        $this->authorize('user-edit');
        $this->normalizeEmail();

        $this->validate([
            'first_name' => ['required', 'string'],
            'last_name' => ['required', 'string'],
            'username' => ['required', Rule::unique('users', 'username')->ignore($this->user->id)],
            'email' => $this->emailRule(),
            'role_name' => $this->roleRule(),
            'company_id' => $this->companyRule(),

            'phone' => ['required'],
            'password' => ['nullable', 'confirmed']
        ]);

        $this->user->first_name = $this->first_name;
        $this->user->last_name = $this->last_name;
        $this->user->username = $this->username;
        $this->user->email = $this->email ?: null;

        $this->user->phone = $this->phone;
        $this->user->company_id = $this->company_id ?: null;
        if ($this->password) {
            $this->user->password = Hash::make($this->password);
        }

        $this->user->syncRoles([$this->role_name]);

        $this->user->save();

        return to_route('users')->with('success', 'User has been updated successfully!');
    }

    public function render()
    {
        return view('livewire.users.user-form');
    }
}
