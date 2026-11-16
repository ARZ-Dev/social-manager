<div>
    <div class="card">
        <div class="card-header border-bottom">
            <h5 class="mb-1">Choose accounts to link to {{ $company->name }}</h5>
            <p class="text-muted mb-0">Pick the Facebook Pages (with their Instagram accounts) and ad accounts this company should read.</p>
        </div>
        <div class="card-body pt-4">
            <h6 class="mb-3">Facebook Pages &amp; Instagram</h6>
            @forelse($pages as $page)
                <label class="d-flex align-items-center border rounded p-3 mb-2 cursor-pointer" wire:key="page-{{ $page['id'] }}">
                    <input type="checkbox" class="form-check-input me-3 mt-0" value="{{ $page['id'] }}" wire:model="selectedPages">
                    @if($page['picture'])
                        <img src="{{ $page['picture'] }}" class="rounded-circle me-3" width="36" height="36" alt="">
                    @endif
                    <div class="flex-grow-1">
                        <div class="fw-semibold"><i class="ti ti-brand-facebook text-primary"></i> {{ $page['name'] }}</div>
                        @if($page['instagram'])
                            <small class="text-muted"><i class="ti ti-brand-instagram text-danger"></i> {{ '@' . $page['instagram']['username'] }} (linked automatically)</small>
                        @else
                            <small class="text-muted">No Instagram business account connected to this Page</small>
                        @endif
                    </div>
                </label>
            @empty
                <p class="text-muted">No Pages found. Make sure you manage at least one Facebook Page and granted access to it.</p>
            @endforelse

            <h6 class="mt-4 mb-3">Ad accounts (for boosts)</h6>
            @forelse($adAccounts as $ad)
                <label class="d-flex align-items-center border rounded p-3 mb-2 cursor-pointer" wire:key="ad-{{ $ad['id'] }}">
                    <input type="checkbox" class="form-check-input me-3 mt-0" value="{{ $ad['id'] }}" wire:model="selectedAds">
                    <div class="flex-grow-1">
                        <div class="fw-semibold"><i class="ti ti-speakerphone"></i> {{ $ad['name'] }}</div>
                        <small class="text-muted">{{ $ad['id'] }} {{ $ad['currency'] ? '· ' . $ad['currency'] : '' }}</small>
                    </div>
                    @unless($ad['active'])
                        <span class="badge bg-label-secondary">Inactive</span>
                    @endunless
                </label>
            @empty
                <p class="text-muted">No ad accounts found.</p>
            @endforelse

            @error('selection') <div class="text-danger mt-2">{{ $message }}</div> @enderror

            <div class="text-end mt-4">
                <button type="button" wire:click="cancel" class="btn btn-label-secondary me-2">Cancel</button>
                <button type="button" wire:click="save" class="btn btn-primary">
                    <span wire:loading wire:target="save" class="spinner-border spinner-border-sm me-1"></span>
                    Link selected
                </button>
            </div>
        </div>
    </div>
</div>
