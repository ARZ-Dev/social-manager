<div>
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @canany(['branch-create', 'branch-edit'])
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">{{ $branchId ? 'Edit' : 'Add' }} Branch - {{ $company->name }}</h5>
            <a href="{{ route('companies') }}" class="btn btn-primary mb-2 text-nowrap">Companies</a>
        </div>
        <div class="card-body">
            <form class="row g-3" wire:submit="save">
                <div class="col-12 col-md-6">
                    <label class="form-label" for="BranchName">Name</label>
                    <input wire:model="name" type="text" id="BranchName" class="form-control" placeholder="Branch name" />
                    @error('name') <div class="text-danger">{{ $message }}</div> @enderror
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="BranchExternalId">POS branch ID <small class="text-muted">(optional)</small></label>
                    <input wire:model="external_id" type="text" id="BranchExternalId" class="form-control" placeholder="Minerva branch ID" />
                    @error('external_id') <div class="text-danger">{{ $message }}</div> @enderror
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="BranchPhone">Phone</label>
                    <input wire:model="phone" type="text" id="BranchPhone" class="form-control" placeholder="202 555 0111" />
                    @error('phone') <div class="text-danger">{{ $message }}</div> @enderror
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="BranchAddress">Address</label>
                    <input wire:model="address" type="text" id="BranchAddress" class="form-control" placeholder="Street, city" />
                    @error('address') <div class="text-danger">{{ $message }}</div> @enderror
                </div>
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input wire:model="is_active" class="form-check-input" type="checkbox" id="BranchActive">
                        <label class="form-check-label" for="BranchActive">Active</label>
                    </div>
                </div>
                <div class="col-12 text-end">
                    @if ($branchId)
                        <button type="button" wire:click="cancel" class="btn btn-label-secondary me-2">Cancel</button>
                    @endif
                    <button type="submit" class="btn btn-primary">{{ $branchId ? 'Update' : 'Add' }} Branch</button>
                </div>
            </form>
        </div>
    </div>
    @endcanany

    <div class="card">
        <div class="card-header border-bottom">
            <h4 class="card-title mb-0">Branches of {{ $company->name }}</h4>
        </div>
        <div class="table-responsive">
            <table class="table border-top">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>POS branch ID</th>
                        <th>Phone</th>
                        <th>Address</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($branches as $branch)
                        <tr wire:key="branch-{{ $branch->id }}">
                            <td>{{ $branch->name }}</td>
                            <td>{{ $branch->external_id ?? '-' }}</td>
                            <td>{{ $branch->phone ?? '-' }}</td>
                            <td>{{ $branch->address ?? '-' }}</td>
                            <td>
                                <span class="badge {{ $branch->is_active ? 'bg-label-success' : 'bg-label-secondary' }}">
                                    {{ $branch->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td>
                                @can('branch-edit')
                                    <a href="#" wire:click.prevent="edit({{ $branch->id }})" class="text-body" title="Edit"><i class="ti ti-edit ti-sm me-2"></i></a>
                                @endcan
                                @can('branch-delete')
                                    <a href="#" class="text-body delete-button" data-id="{{ $branch->id }}" title="Delete"><i class="ti ti-trash ti-sm mx-2 text-danger"></i></a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No branches yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @script
        @include('livewire.deleteConfirm')
    @endscript
</div>
