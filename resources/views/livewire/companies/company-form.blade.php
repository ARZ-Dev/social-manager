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
                <div class="col-12 text-end">
                    <button type="submit" class="btn btn-primary">Submit</button>
                </div>
            </form>
        </div>
    </div>
</div>
