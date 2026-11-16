<?php

namespace App\Livewire\Companies;

use App\Models\Company;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
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

    /** Branch rows edited inline: [id, name, external_id, phone, address, is_active]. */
    public array $branches = [];

    public function mount($id = 0)
    {
        $this->authorize($id ? 'company-edit' : 'company-create');

        // Only Super Admins can create new companies.
        abort_if(!$id && !auth()->user()->canAccessAllCompanies(), 403);

        if ($id) {
            $this->editing = true;
            $this->company = Company::visibleTo(auth()->user())->findOrFail($id);
            $this->name = $this->company->name;
            $this->email = $this->company->email ?? '';
            $this->phone = $this->company->phone ?? '';
            $this->address = $this->company->address ?? '';
            $this->is_active = $this->company->is_active;

            $this->branches = $this->company->branches()->orderBy('name')->get()
                ->map(fn ($b) => [
                    'id' => $b->id,
                    'name' => $b->name,
                    'external_id' => $b->external_id ?? '',
                    'phone' => $b->phone ?? '',
                    'address' => $b->address ?? '',
                    'is_active' => $b->is_active,
                ])->all();
        }
    }

    public function addBranch()
    {
        $this->authorize('branch-create');
        $this->branches[] = ['id' => null, 'name' => '', 'external_id' => '', 'phone' => '', 'address' => '', 'is_active' => true];
    }

    public function removeBranch($index)
    {
        if (!empty($this->branches[$index]['id'])) {
            $this->authorize('branch-delete');
        }
        unset($this->branches[$index]);
        $this->branches = array_values($this->branches);
        $this->resetValidation();
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'branches' => ['array'],
            'branches.*.name' => ['required', 'string', 'max:255'],
            'branches.*.external_id' => ['nullable', 'string', 'max:255', 'distinct'],
            'branches.*.phone' => ['nullable', 'string', 'max:50'],
            'branches.*.address' => ['nullable', 'string', 'max:255'],
            'branches.*.is_active' => ['boolean'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'branches.*.name' => 'branch name',
            'branches.*.external_id' => 'branch external ID',
            'branches.*.phone' => 'branch phone',
            'branches.*.address' => 'branch address',
        ];
    }

    public function save()
    {
        $this->authorize($this->editing ? 'company-edit' : 'company-create');
        abort_if(!$this->editing && !auth()->user()->canAccessAllCompanies(), 403);
        $data = $this->validate();
        $branches = $data['branches'] ?? [];
        unset($data['branches']);

        DB::transaction(function () use ($data, $branches) {
            if ($this->editing) {
                $this->company->update($data);
            } else {
                $this->company = Company::create($data);
            }

            $this->syncBranches($branches);
        });

        $message = $this->editing ? 'Company has been updated successfully!' : 'Company has been created successfully!';

        return to_route('companies')->with('success', $message);
    }

    /**
     * Update kept branches, create new rows, and delete rows the user removed.
     */
    protected function syncBranches(array $rows): void
    {
        $existing = $this->company->branches()->get()->keyBy('id');
        $keptIds = collect($rows)->pluck('id')->filter()->all();

        $removed = $existing->except($keptIds);
        if ($removed->isNotEmpty()) {
            $this->authorize('branch-delete');
            $removed->each->delete();
        }

        foreach ($rows as $row) {
            $attrs = [
                'name' => $row['name'],
                'external_id' => ($row['external_id'] ?? '') ?: null,
                'phone' => ($row['phone'] ?? '') ?: null,
                'address' => ($row['address'] ?? '') ?: null,
                'is_active' => (bool) ($row['is_active'] ?? true),
            ];

            if (!empty($row['id']) && $existing->has($row['id'])) {
                $branch = $existing[$row['id']];
                $branch->fill($attrs);
                if ($branch->isDirty()) {
                    $this->authorize('branch-edit');
                    $branch->save();
                }
            } else {
                $this->authorize('branch-create');
                $this->company->branches()->create($attrs);
            }
        }
    }

    public function render()
    {
        return view('livewire.companies.company-form');
    }
}
