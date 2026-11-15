<?php

namespace App\Livewire\Companies;

use App\Models\Company;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Lists a company's branches and adds/edits them with an inline form.
 */
class BranchView extends Component
{
    use AuthorizesRequests;

    public Company $company;

    public ?int $branchId = null;
    public string $name = '';
    public string $external_id = '';
    public string $phone = '';
    public string $address = '';
    public bool $is_active = true;

    protected $listeners = ['delete'];

    public function mount($company)
    {
        $this->authorize('branch-list');
        $this->company = Company::findOrFail($company);
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'external_id' => [
                'nullable', 'string', 'max:255',
                Rule::unique('branches', 'external_id')
                    ->where('company_id', $this->company->id)
                    ->ignore($this->branchId),
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }

    public function edit($id)
    {
        $this->authorize('branch-edit');
        $branch = $this->company->branches()->findOrFail($id);

        $this->branchId = $branch->id;
        $this->name = $branch->name;
        $this->external_id = $branch->external_id ?? '';
        $this->phone = $branch->phone ?? '';
        $this->address = $branch->address ?? '';
        $this->is_active = $branch->is_active;
        $this->resetValidation();
    }

    public function cancel()
    {
        $this->reset(['branchId', 'name', 'external_id', 'phone', 'address', 'is_active']);
        $this->resetValidation();
    }

    public function save()
    {
        $this->authorize($this->branchId ? 'branch-edit' : 'branch-create');
        $data = $this->validate();
        $data['external_id'] = $data['external_id'] ?: null;

        if ($this->branchId) {
            $this->company->branches()->findOrFail($this->branchId)->update($data);
            session()->flash('success', 'Branch has been updated successfully!');
        } else {
            $this->company->branches()->create($data);
            session()->flash('success', 'Branch has been created successfully!');
        }

        $this->cancel();
    }

    public function delete($id)
    {
        $this->authorize('branch-delete');
        $this->company->branches()->findOrFail($id)->delete();

        if ($this->branchId == $id) {
            $this->cancel();
        }
        session()->flash('success', 'Branch has been deleted successfully!');
    }

    public function render()
    {
        return view('livewire.companies.branch-view', [
            'branches' => $this->company->branches()->orderBy('name')->get(),
        ]);
    }
}
