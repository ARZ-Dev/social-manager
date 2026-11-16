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
                {{-- Meta app (per company) --}}
                <div class="col-12" id="meta-app">
                    <hr class="my-2" />
                    <h6 class="mb-1"><i class="ti ti-brand-meta me-1"></i> Meta app (Facebook &amp; Instagram)</h6>
                    <p class="text-muted small mb-3">This company connects its Facebook Pages, Instagram accounts and ad accounts through its own Meta app.</p>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="MetaAppId">App ID</label>
                    <input wire:model="meta_app_id" type="text" inputmode="numeric" id="MetaAppId" class="form-control" placeholder="e.g. 1234567890123456" autocomplete="off" />
                    @error('meta_app_id') <div class="text-danger">{{ $message }}</div> @enderror
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="MetaAppSecret">App Secret</label>
                    <input wire:model="meta_app_secret" type="password" id="MetaAppSecret" class="form-control"
                        placeholder="{{ $hasMetaSecret ? 'Saved. Leave blank to keep it' : '32-character secret' }}" autocomplete="new-password" />
                    @error('meta_app_secret') <div class="text-danger">{{ $message }}</div> @enderror
                    <small class="text-muted">Stored encrypted. Never shown again after saving. Clear the App ID to remove both.</small>
                </div>
                <div class="col-12">
                    <details class="border rounded p-3 bg-lighter">
                        <summary class="fw-semibold">How do I get the App ID and App Secret?</summary>
                        <ol class="mt-3 mb-2 small">
                            <li class="mb-2">Go to <a href="https://developers.facebook.com/apps" target="_blank" rel="noopener">developers.facebook.com/apps</a> and log in with the Facebook account that manages this company's Page. If asked, register as a developer (free).</li>
                            <li class="mb-2">Click <strong>Create app</strong>. Choose the <strong>Other</strong> use case, then app type <strong>Business</strong>. Name it (e.g. "{{ $name ?: 'Company' }} Social Manager") and select the company's Business portfolio if it has one.</li>
                            <li class="mb-2">In the app dashboard, add these products: <strong>Facebook Login for Business</strong>, <strong>Instagram</strong> (API setup with Facebook login), <strong>Messenger</strong> and <strong>Marketing API</strong>.</li>
                            <li class="mb-2">Open <strong>Facebook Login for Business → Settings</strong> and add this exact address under <strong>Valid OAuth Redirect URIs</strong>, then save:
                                <div class="input-group input-group-sm mt-1" style="max-width: 560px">
                                    <input type="text" class="form-control" value="{{ route('social.callback') }}" readonly id="MetaCallbackUrl">
                                    <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText(document.getElementById('MetaCallbackUrl').value); this.innerText='Copied'">Copy</button>
                                </div>
                                @if(!str_starts_with(route('social.callback'), 'https://') && !str_contains(route('social.callback'), 'localhost'))
                                    <span class="text-warning">Meta only accepts HTTPS addresses (except localhost). Serve the app over HTTPS before connecting.</span>
                                @endif
                            </li>
                            <li class="mb-2">Go to <strong>App settings → Basic</strong>. Copy the <strong>App ID</strong>, then click <strong>Show</strong> next to <strong>App Secret</strong> (Facebook asks for your password) and copy it. Paste both here and click Submit.</li>
                            <li class="mb-2">On the same page, fill in <strong>Privacy Policy URL</strong>, <strong>App icon</strong> and <strong>Category</strong>. Meta needs these before the app can go live.</li>
                            <li class="mb-2"><strong>Testing:</strong> while the app is in <em>Development</em> mode, only people added under <strong>App roles → Roles</strong> can connect. Add the people who will click "Connect".</li>
                            <li><strong>Going live:</strong> to connect accounts of people without an app role, request these permissions in <strong>App Review → Permissions and features</strong>, complete <strong>Business verification</strong>, then switch the app to <em>Live</em>:
                                <div class="mt-1"><code>{{ implode(', ', config('meta.scopes')) }}</code></div>
                            </li>
                        </ol>
                        <p class="small text-muted mb-0">Then open <strong>Social Media</strong> in the menu, choose this company and click <strong>Connect Facebook &amp; Instagram</strong>.</p>
                    </details>
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
