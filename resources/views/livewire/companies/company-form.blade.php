<div>
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">{{ $editing ? 'Edit' : 'Add' }} Company</h5>
            <a href="{{ route('companies') }}" class="btn btn-primary mb-2 text-nowrap">Companies</a>
        </div>
        <div class="card-body">
            <form class="row g-3" wire:submit="save">
                <div class="col-12 col-md-6">
                    <label class="form-label" for="CompanyName">Name</label>
                    <input wire:model="name" type="text" id="CompanyName" class="form-control" placeholder="Company name" />
                    @error('name') <div class="text-danger">{{ $message }}</div> @enderror
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="CompanyEmail">E-mail</label>
                    <input wire:model="email" type="email" id="CompanyEmail" class="form-control" placeholder="info@company.com" />
                    @error('email') <div class="text-danger">{{ $message }}</div> @enderror
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="CompanyPhone">Phone</label>
                    <input wire:model="phone" type="text" id="CompanyPhone" class="form-control" placeholder="202 555 0111" />
                    @error('phone') <div class="text-danger">{{ $message }}</div> @enderror
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="CompanyAddress">Address</label>
                    <input wire:model="address" type="text" id="CompanyAddress" class="form-control" placeholder="Street, city" />
                    @error('address') <div class="text-danger">{{ $message }}</div> @enderror
                </div>
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input wire:model="is_active" class="form-check-input" type="checkbox" id="CompanyActive">
                        <label class="form-check-label" for="CompanyActive">Active</label>
                    </div>
                </div>
                @can('branch-list')
                <div class="col-12">
                    <hr class="my-2" />
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0">Branches</h6>
                        @can('branch-create')
                            <button type="button" wire:click="addBranch" class="btn btn-sm btn-label-primary">
                                <i class="ti ti-plus ti-xs me-1"></i> Add Branch
                            </button>
                        @endcan
                    </div>

                    @forelse($branches as $i => $branch)
                        <div class="border rounded p-3 mb-2" wire:key="branch-{{ $branch['id'] ?? 'new' }}-{{ $i }}">
                            <div class="row g-2 align-items-end">
                                <div class="col-12 col-md-3">
                                    <label class="form-label">Name</label>
                                    <input wire:model="branches.{{ $i }}.name" type="text" class="form-control" placeholder="Branch name" />
                                    @error("branches.$i.name") <div class="text-danger">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-12 col-md-2">
                                    <label class="form-label">External ID</label>
                                    <input wire:model="branches.{{ $i }}.external_id" type="text" class="form-control" />
                                    @error("branches.$i.external_id") <div class="text-danger">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-12 col-md-2">
                                    <label class="form-label">Phone</label>
                                    <input wire:model="branches.{{ $i }}.phone" type="text" class="form-control" />
                                    @error("branches.$i.phone") <div class="text-danger">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-12 col-md-3">
                                    <label class="form-label">Address</label>
                                    <input wire:model="branches.{{ $i }}.address" type="text" class="form-control" />
                                    @error("branches.$i.address") <div class="text-danger">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-8 col-md-1">
                                    <div class="form-check form-switch mb-2">
                                        <input wire:model="branches.{{ $i }}.is_active" class="form-check-input" type="checkbox" id="BranchActive{{ $i }}">
                                        <label class="form-check-label" for="BranchActive{{ $i }}">Active</label>
                                    </div>
                                </div>
                                <div class="col-4 col-md-1 text-end">
                                    @if(empty($branch['id']) || auth()->user()->can('branch-delete'))
                                        <button type="button" wire:click="removeBranch({{ $i }})" class="btn btn-icon btn-label-danger" title="Remove branch">
                                            <i class="ti ti-trash"></i>
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="text-muted mb-0">No branches yet.</p>
                    @endforelse
                </div>
                @endcan
                <div class="col-12 text-end">
                    <button type="submit" class="btn btn-primary">Submit</button>
                </div>
            </form>
        </div>
    </div>
</div>
