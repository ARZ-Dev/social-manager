<?php

namespace App\Livewire\Companies;

use App\Models\Company;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

class CompanyView extends Component
{
    use AuthorizesRequests;

    protected $listeners = ['delete'];

    public function mount()
    {
        $this->authorize('company-list');
    }

    public function delete($id)
    {
        $this->authorize('company-delete');

        Company::visibleTo(auth()->user())->findOrFail($id)->delete();

        return to_route('companies')->with('success', 'Company has been deleted successfully!');
    }

    public function render()
    {
        return view('livewire.companies.company-view', [
            'companies' => Company::visibleTo(auth()->user())->withCount(['branches', 'users'])->latest()->get(),
        ]);
    }
}
