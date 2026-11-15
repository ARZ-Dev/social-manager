<?php

namespace App\Livewire\Companies;

use App\Models\Company;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

class CompanyForm extends Component
{
    use AuthorizesRequests;

    public ?Company $company = null;

    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public string $address = '';
    public bool $is_active = true;

    public bool $editing = false;

    public function mount($id = 0)
    {
        $this->authorize($id ? 'company-edit' : 'company-create');

        if ($id) {
            $this->editing = true;
            $this->company = Company::findOrFail($id);
            $this->name = $this->company->name;
            $this->email = $this->company->email ?? '';
            $this->phone = $this->company->phone ?? '';
            $this->address = $this->company->address ?? '';
            $this->is_active = $this->company->is_active;
        }
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }

    public function save()
    {
        $this->authorize($this->editing ? 'company-edit' : 'company-create');
        $data = $this->validate();

        if ($this->editing) {
            $this->company->update($data);
            $message = 'Company has been updated successfully!';
        } else {
            Company::create($data);
            $message = 'Company has been created successfully!';
        }

        return to_route('companies')->with('success', $message);
    }

    public function render()
    {
        return view('livewire.companies.company-form');
    }
}
