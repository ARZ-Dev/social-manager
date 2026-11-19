<?php

namespace App\Livewire\Companies;

use App\Models\Branch;
use App\Models\Company;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
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

    /** Meta (Facebook) app used to connect this company's Pages and Instagram. */
    public string $meta_app_id = '';
    /** Write-only: never loaded back into the page. Blank = keep the saved secret. */
    public string $meta_app_secret = '';
    public bool $hasMetaSecret = false;

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
            $this->meta_app_id = $this->company->meta_app_id ?? '';
            $this->hasMetaSecret = filled($this->company->meta_app_secret);

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
            'meta_app_id' => ['nullable', 'regex:/^\d{5,20}$/'],
            'meta_app_secret' => [
                'nullable', 'regex:/^[a-f0-9]{32}$/i',
                // A new App ID needs a secret, unless one is already saved.
                \Illuminate\Validation\Rule::requiredIf(fn () => filled($this->meta_app_id) && !$this->hasMetaSecret),
            ],
            'branches' => ['array'],
            // validate() only returns keys that have a rule: without this, every
            // row lost its id, so each save deleted and re-inserted all branches.
            'branches.*.id' => ['nullable', 'integer'],
            'branches.*.name' => ['required', 'string', 'max:255'],
            // The unique index compares case-insensitively, so duplicates must too.
            'branches.*.external_id' => ['nullable', 'string', 'max:255', 'distinct:ignore_case'],
            'branches.*.phone' => ['nullable', 'string', 'max:50'],
            'branches.*.address' => ['nullable', 'string', 'max:255'],
            'branches.*.is_active' => ['boolean'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'meta_app_id' => 'Meta App ID',
            'meta_app_secret' => 'Meta App Secret',
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

        // Livewire skips the TrimStrings middleware, and MySQL ignores trailing
        // spaces when comparing, so "123 " would collide with "123".
        foreach ($this->branches as $i => $branch) {
            $this->branches[$i]['external_id'] = trim((string) ($branch['external_id'] ?? ''));
        }

        $data = $this->validate();
        $branches = $data['branches'] ?? [];
        unset($data['branches']);

        // Blank secret keeps the saved one; removing the App ID removes both.
        $data['meta_app_id'] = $data['meta_app_id'] ?: null;
        if (!$data['meta_app_id']) {
            $data['meta_app_secret'] = null;
        } elseif (blank($data['meta_app_secret'])) {
            unset($data['meta_app_secret']);
        }

        try {
            DB::transaction(function () use ($data, $branches) {
                if ($this->editing) {
                    $this->company->update($data);
                } else {
                    $this->company = Company::create($data);
                }

                $this->syncBranches($branches);
            });
        } catch (UniqueConstraintViolationException) {
            // Fallback for IDs the database still treats as equal (e.g. "é" and "e").
            $this->addError('branches', 'Two branches cannot share the same external ID.');
            return;
        }

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

        $rows = collect($rows)->map(fn ($row) => [
            'id' => !empty($row['id']) && $existing->has($row['id']) ? $row['id'] : null,
            'attrs' => [
                'name' => $row['name'],
                'external_id' => ($row['external_id'] ?? '') ?: null,
                'phone' => ($row['phone'] ?? '') ?: null,
                'address' => ($row['address'] ?? '') ?: null,
                'is_active' => (bool) ($row['is_active'] ?? true),
            ],
        ]);

        $this->releaseExternalIds($rows);

        foreach ($rows as ['id' => $id, 'attrs' => $attrs]) {
            if ($id) {
                $branch = $existing[$id];
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

    /**
     * External IDs are unique per company, deleted branches included. Before
     * writing, clear each ID the form assigns from whichever row holds it now
     * (a deleted branch, or a kept one moving to another ID), so swapping IDs
     * between branches or reusing a deleted branch's ID doesn't collide.
     */
    protected function releaseExternalIds(Collection $rows): void
    {
        $wanted = $rows->pluck('attrs.external_id')->filter()->values();
        if ($wanted->isEmpty()) {
            return;
        }

        $finalIds = $rows->filter(fn ($row) => $row['id'])
            ->mapWithKeys(fn ($row) => [$row['id'] => $row['attrs']['external_id']]);

        $release = Branch::withTrashed()
            ->where('company_id', $this->company->id)
            ->whereIn('external_id', $wanted)
            ->get(['id', 'external_id'])
            ->reject(fn ($branch) => $finalIds->get($branch->id) === $branch->external_id)
            ->pluck('id');

        if ($release->isNotEmpty()) {
            Branch::withTrashed()->whereKey($release)->update(['external_id' => null]);
        }
    }

    public function render()
    {
        return view('livewire.companies.company-form');
    }
}
