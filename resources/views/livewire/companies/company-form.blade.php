@php
    $callback = route('social.callback');
    $err = fn ($field) => $errors->has($field) ? 'input input-error' : 'input';
@endphp

<div>
    <x-ui.page-header :title="$editing ? 'Edit ' . ($company?->name ?? 'company') : 'Add company'" :back="route('companies')" />

    <form wire:submit="save" class="space-y-6" novalidate>
        {{-- Details --}}
        <section class="grid gap-6 lg:grid-cols-3">
            <div>
                <h2 class="text-base font-semibold">Details</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">How the company appears across the app.</p>
            </div>
            <div class="card p-5 lg:col-span-2">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="label" for="CompanyName">Name</label>
                        <input wire:model="name" type="text" id="CompanyName" class="{{ $err('name') }}" placeholder="Company name" required>
                        @error('name') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label" for="CompanyEmail">E-mail</label>
                        <input wire:model="email" type="email" id="CompanyEmail" class="{{ $err('email') }}" placeholder="info@company.com">
                        @error('email') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label" for="CompanyPhone">Phone</label>
                        <input wire:model="phone" type="tel" id="CompanyPhone" class="{{ $err('phone') }}" placeholder="202 555 0111">
                        @error('phone') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label" for="CompanyAddress">Address</label>
                        <input wire:model="address" type="text" id="CompanyAddress" class="{{ $err('address') }}" placeholder="Street, city">
                        @error('address') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <label class="flex items-center gap-3 sm:col-span-2">
                        <input wire:model="is_active" type="checkbox" class="checkbox" id="CompanyActive">
                        <span class="text-sm">
                            <span class="font-medium text-slate-900 dark:text-white">Active</span>
                            <span class="block text-xs text-slate-500 dark:text-slate-400">Inactive companies can't be picked for new users.</span>
                        </span>
                    </label>
                </div>
            </div>
        </section>

        {{-- Meta app --}}
        <section class="grid gap-6 lg:grid-cols-3" id="meta-app">
            <div>
                <h2 class="flex items-center gap-2 text-base font-semibold"><x-icon name="brand-meta" class="size-5 text-brand-600 dark:text-brand-400" /> Meta app</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">The company connects its Facebook Pages, Instagram accounts and ad accounts through its own Meta app.</p>
                <p class="mt-3">
                    @if($meta_app_id && $hasMetaSecret)
                        <span class="badge badge-green"><x-icon name="circle-check" class="size-3.5" /> App ID and secret saved</span>
                    @else
                        <span class="badge badge-gray">Not set up</span>
                    @endif
                </p>
            </div>
            <div class="card p-5 lg:col-span-2">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label" for="MetaAppId">App ID</label>
                        <input wire:model="meta_app_id" type="text" inputmode="numeric" id="MetaAppId" class="{{ $err('meta_app_id') }}" placeholder="e.g. 1234567890123456" autocomplete="off">
                        @error('meta_app_id') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div x-data="{ show: false }">
                        <label class="label" for="MetaAppSecret">App Secret</label>
                        <div class="relative">
                            <input wire:model="meta_app_secret" :type="show ? 'text' : 'password'" type="password" id="MetaAppSecret" class="{{ $err('meta_app_secret') }} pr-10"
                                placeholder="{{ $hasMetaSecret ? 'Saved. Leave blank to keep it' : '32-character secret' }}" autocomplete="new-password">
                            <button type="button" class="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                                x-on:click="show = !show" :aria-label="show ? 'Hide secret' : 'Show secret'">
                                <span x-show="!show"><x-icon name="eye" class="size-4" /></span>
                                <span x-show="show" x-cloak><x-icon name="eye-off" class="size-4" /></span>
                            </button>
                        </div>
                        @error('meta_app_secret') <p class="field-error">{{ $message }}</p> @enderror
                        <p class="help">Stored encrypted and never shown again. Clear the App ID to remove both.</p>
                    </div>

                    <div class="sm:col-span-2">
                        <label class="label" for="MetaCallbackUrl">OAuth redirect URL</label>
                        <div class="flex gap-2" x-data="{ copied: false }">
                            <input type="text" id="MetaCallbackUrl" class="input font-mono text-xs" value="{{ $callback }}" readonly>
                            <button type="button" class="btn btn-secondary shrink-0" x-on:click="navigator.clipboard.writeText(@js($callback)); copied = true; setTimeout(() => copied = false, 2000)">
                                <span x-show="!copied"><x-icon name="copy" class="size-4" /></span>
                                <span x-show="copied" x-cloak><x-icon name="check" class="size-4" /></span>
                                <span x-text="copied ? 'Copied' : 'Copy'">Copy</span>
                            </button>
                        </div>
                        <p class="help">Add this under Facebook Login for Business → Settings → Valid OAuth Redirect URIs.</p>
                        @if(!str_starts_with($callback, 'https://') && !str_contains($callback, 'localhost'))
                            <p class="field-error">Meta only accepts HTTPS addresses (except localhost). Serve the app over HTTPS before connecting.</p>
                        @endif
                    </div>

                    <details class="group rounded-lg border border-slate-200 bg-slate-50 sm:col-span-2 dark:border-slate-800 dark:bg-slate-800/40">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-2 px-4 py-3 text-sm font-medium text-slate-800 dark:text-slate-200">
                            How do I get the App ID and App Secret?
                            <x-icon name="chevron-down" class="size-4 text-slate-400 transition-transform group-open:rotate-180" />
                        </summary>
                        <ol class="list-decimal space-y-2 px-4 pb-4 pl-8 text-sm text-slate-600 dark:text-slate-400">
                            <li>Go to <a href="https://developers.facebook.com/apps" target="_blank" rel="noopener" class="link">developers.facebook.com/apps</a> and log in with the Facebook account that manages this company's Page. Register as a developer if asked (free).</li>
                            <li>Click <strong>Create app</strong>, choose the <strong>Other</strong> use case, then app type <strong>Business</strong>. Name it (e.g. "{{ $name ?: 'Company' }} Social Manager") and pick the company's Business portfolio if it has one.</li>
                            <li>Add these products: <strong>Facebook Login for Business</strong>, <strong>Instagram</strong> (API setup with Facebook login), <strong>Messenger</strong> and <strong>Marketing API</strong>.</li>
                            <li>In <strong>Facebook Login for Business → Settings</strong>, add the redirect URL above under <strong>Valid OAuth Redirect URIs</strong> and save.</li>
                            <li>In <strong>App settings → Basic</strong>, add your domain under <strong>App domains</strong>, copy the <strong>App ID</strong>, click <strong>Show</strong> next to <strong>App Secret</strong> and copy it. Paste both here.</li>
                            <li>On the same page, fill in <strong>Privacy Policy URL</strong>, <strong>App icon</strong> and <strong>Category</strong>. Meta needs these before going live.</li>
                            <li><strong>Testing:</strong> in Development mode, only people listed under <strong>App roles → Roles</strong> can connect.</li>
                            <li><strong>Going live:</strong> request these permissions in <strong>App Review</strong>, complete <strong>Business verification</strong>, then switch the app to Live:
                                <code class="mt-1 block break-words rounded bg-white p-2 text-xs dark:bg-slate-900">{{ implode(', ', config('meta.scopes')) }}</code>
                            </li>
                        </ol>
                    </details>
                </div>
            </div>
        </section>

        {{-- Google Ads. Don't use an id/class like "google-ads" or "ads" here: ad blockers
             hide those (EasyList ###google-ads), and the whole section disappears. --}}
        @php $googleCallback = route('social.google.callback'); @endphp
        <section class="grid gap-6 lg:grid-cols-3" id="google-integration">
            <div>
                <h2 class="flex items-center gap-2 text-base font-semibold"><x-icon name="brand-google" class="size-5 text-[#34A853]" /> Google Ads</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">The company connects its Google Ads accounts through its own Google Cloud OAuth client and Google Ads developer token.</p>
                <p class="mt-3">
                    @if($google_ads_client_id && $hasGoogleSecret && $hasGoogleToken)
                        <span class="badge badge-green"><x-icon name="circle-check" class="size-3.5" /> Client and token saved</span>
                    @else
                        <span class="badge badge-gray">Not set up</span>
                    @endif
                </p>
            </div>
            <div class="card p-5 lg:col-span-2">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="label" for="GoogleClientId">OAuth client ID</label>
                        <input wire:model="google_ads_client_id" type="text" id="GoogleClientId" class="{{ $err('google_ads_client_id') }}" placeholder="1234567890-abc123.apps.googleusercontent.com" autocomplete="off" spellcheck="false">
                        @error('google_ads_client_id') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div x-data="{ show: false }">
                        <label class="label" for="GoogleClientSecret">OAuth client secret</label>
                        <div class="relative">
                            <input wire:model="google_ads_client_secret" :type="show ? 'text' : 'password'" type="password" id="GoogleClientSecret" class="{{ $err('google_ads_client_secret') }} pr-10"
                                placeholder="{{ $hasGoogleSecret ? 'Saved. Leave blank to keep it' : 'GOCSPX-…' }}" autocomplete="new-password" spellcheck="false">
                            <button type="button" class="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                                x-on:click="show = !show" :aria-label="show ? 'Hide secret' : 'Show secret'">
                                <span x-show="!show"><x-icon name="eye" class="size-4" /></span>
                                <span x-show="show" x-cloak><x-icon name="eye-off" class="size-4" /></span>
                            </button>
                        </div>
                        @error('google_ads_client_secret') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div x-data="{ show: false }">
                        <label class="label" for="GoogleDevToken">Developer token</label>
                        <div class="relative">
                            <input wire:model="google_ads_developer_token" :type="show ? 'text' : 'password'" type="password" id="GoogleDevToken" class="{{ $err('google_ads_developer_token') }} pr-10"
                                placeholder="{{ $hasGoogleToken ? 'Saved. Leave blank to keep it' : '22-character token' }}" autocomplete="new-password" spellcheck="false">
                            <button type="button" class="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                                x-on:click="show = !show" :aria-label="show ? 'Hide token' : 'Show token'">
                                <span x-show="!show"><x-icon name="eye" class="size-4" /></span>
                                <span x-show="show" x-cloak><x-icon name="eye-off" class="size-4" /></span>
                            </button>
                        </div>
                        @error('google_ads_developer_token') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <p class="help sm:col-span-2 -mt-2">The secret and token are stored encrypted and never shown again. Clear the client ID to remove all three.</p>

                    <div class="sm:col-span-2">
                        <label class="label" for="GoogleCallbackUrl">Authorized redirect URI</label>
                        <div class="flex gap-2" x-data="{ copied: false }">
                            <input type="text" id="GoogleCallbackUrl" class="input font-mono text-xs" value="{{ $googleCallback }}" readonly>
                            <button type="button" class="btn btn-secondary shrink-0" x-on:click="navigator.clipboard.writeText(@js($googleCallback)); copied = true; setTimeout(() => copied = false, 2000)">
                                <span x-show="!copied"><x-icon name="copy" class="size-4" /></span>
                                <span x-show="copied" x-cloak><x-icon name="check" class="size-4" /></span>
                                <span x-text="copied ? 'Copied' : 'Copy'">Copy</span>
                            </button>
                        </div>
                        <p class="help">Add this to the OAuth client under <strong>Authorized redirect URIs</strong>.</p>
                        @if(!str_starts_with($googleCallback, 'https://') && !str_contains($googleCallback, 'localhost'))
                            <p class="field-error">Google only accepts HTTPS addresses (except localhost). Serve the app over HTTPS before connecting.</p>
                        @endif
                    </div>

                    <details class="group rounded-lg border border-slate-200 bg-slate-50 sm:col-span-2 dark:border-slate-800 dark:bg-slate-800/40">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-2 px-4 py-3 text-sm font-medium text-slate-800 dark:text-slate-200">
                            How do I get the OAuth client and developer token?
                            <x-icon name="chevron-down" class="size-4 text-slate-400 transition-transform group-open:rotate-180" />
                        </summary>
                        <ol class="list-decimal space-y-2 px-4 pb-4 pl-8 text-sm text-slate-600 dark:text-slate-400">
                            <li><strong>Developer token:</strong> sign in to a Google Ads <em>manager</em> account (create one free at <a href="https://ads.google.com/home/tools/manager-accounts/" target="_blank" rel="noopener" class="link">ads.google.com/home/tools/manager-accounts</a>), open <strong>Admin → API Center</strong>, accept the terms and copy the token. A new token only works with test accounts: click <strong>Apply for Basic access</strong> to use it with real accounts (Google reviews it in a few days).</li>
                            <li><strong>Google Cloud project:</strong> at <a href="https://console.cloud.google.com/" target="_blank" rel="noopener" class="link">console.cloud.google.com</a>, create a project and enable the <strong>Google Ads API</strong> (APIs &amp; Services → Library).</li>
                            <li><strong>Consent screen:</strong> under <strong>Google Auth Platform → Branding / Audience</strong>, name the app and set the publishing status to <strong>In production</strong>. While it says <em>Testing</em>, Google signs the company out after 7 days.</li>
                            <li><strong>OAuth client:</strong> under <strong>Clients → Create client</strong>, choose <strong>Web application</strong>, add the redirect URI above, then copy the <strong>client ID</strong> and <strong>client secret</strong> here.</li>
                            <li>Back on Social Media, choose <strong>Connect → Google Ads</strong> and sign in with a Google account that can open the company's Google Ads accounts (or their manager account).</li>
                        </ol>
                    </details>
                </div>
            </div>
        </section>

        {{-- Branches --}}
        @can('branch-list')
            <section class="grid gap-6 lg:grid-cols-3">
                <div>
                    <h2 class="text-base font-semibold">Branches</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Locations or units of this company.</p>
                </div>
                <div class="card lg:col-span-2">
                    @error('branches') <p class="field-error px-5 pt-4">{{ $message }}</p> @enderror
                    <div class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse($branches as $i => $branch)
                            <div class="p-5" wire:key="branch-{{ $branch['id'] ?? 'new' }}-{{ $i }}">
                                <div class="grid gap-3 sm:grid-cols-6">
                                    <div class="sm:col-span-3">
                                        <label class="label" for="BranchName{{ $i }}">Name</label>
                                        <input wire:model="branches.{{ $i }}.name" id="BranchName{{ $i }}" type="text" class="{{ $err("branches.$i.name") }}" placeholder="Branch name">
                                        @error("branches.$i.name") <p class="field-error">{{ $message }}</p> @enderror
                                    </div>
                                    <div class="sm:col-span-3">
                                        <label class="label" for="BranchExt{{ $i }}">External ID</label>
                                        <input wire:model="branches.{{ $i }}.external_id" id="BranchExt{{ $i }}" type="text" class="{{ $err("branches.$i.external_id") }}">
                                        @error("branches.$i.external_id") <p class="field-error">{{ $message }}</p> @enderror
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label class="label" for="BranchPhone{{ $i }}">Phone</label>
                                        <input wire:model="branches.{{ $i }}.phone" id="BranchPhone{{ $i }}" type="tel" class="{{ $err("branches.$i.phone") }}">
                                        @error("branches.$i.phone") <p class="field-error">{{ $message }}</p> @enderror
                                    </div>
                                    <div class="sm:col-span-4">
                                        <label class="label" for="BranchAddress{{ $i }}">Address</label>
                                        <input wire:model="branches.{{ $i }}.address" id="BranchAddress{{ $i }}" type="text" class="{{ $err("branches.$i.address") }}">
                                        @error("branches.$i.address") <p class="field-error">{{ $message }}</p> @enderror
                                    </div>
                                </div>
                                <div class="mt-3 flex items-center justify-between">
                                    <label class="flex items-center gap-2 text-sm">
                                        <input wire:model="branches.{{ $i }}.is_active" type="checkbox" class="checkbox"> Active
                                    </label>
                                    @if(empty($branch['id']) || auth()->user()->can('branch-delete'))
                                        <button type="button" wire:click="removeBranch({{ $i }})" class="btn btn-ghost btn-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10">
                                            <x-icon name="trash" class="size-4" /> Remove
                                        </button>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <x-ui.empty icon="map-pin" title="No branches yet" compact />
                        @endforelse
                    </div>
                    @can('branch-create')
                        <div class="border-t border-slate-200 p-3 dark:border-slate-800">
                            <button type="button" wire:click="addBranch" class="btn btn-ghost btn-sm w-full text-brand-700 dark:text-brand-300">
                                <x-icon name="plus" class="size-4" /> Add branch
                            </button>
                        </div>
                    @endcan
                </div>
            </section>
        @endcan

        <div class="sticky bottom-0 -mx-4 flex justify-end gap-2 border-t border-slate-200 bg-slate-50/90 px-4 py-4 backdrop-blur sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8 dark:border-slate-800 dark:bg-slate-950/90">
            <a href="{{ route('companies') }}" wire:navigate class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading wire:target="save" class="size-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                {{ $editing ? 'Save changes' : 'Create company' }}
            </button>
        </div>
    </form>
</div>
