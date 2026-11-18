@php
    $viewOnly = $status == \App\Utils\Constants::VIEW_STATUS;
    $heading = !$editing ? 'Add user' : ($viewOnly ? 'View user' : ($status == \App\Utils\Constants::CONFIRM_STATUS ? 'Confirm user' : 'Edit user'));
    $err = fn ($field) => $errors->has($field) ? 'input input-error' : 'input';
    $selErr = fn ($field) => $errors->has($field) ? 'select input-error' : 'select';
@endphp

<div>
    <x-ui.page-header :title="$heading" :subtitle="$editing ? trim($first_name . ' ' . $last_name) : null" :back="route('users')" />

    <form wire:submit="{{ $editing ? 'update' : 'store' }}" class="space-y-6" novalidate>
        <fieldset @disabled($viewOnly) class="space-y-6">
            {{-- Profile --}}
            <section class="grid gap-6 lg:grid-cols-3">
                <div>
                    <h2 class="text-base font-semibold">Profile</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Name and contact details.</p>
                </div>
                <div class="card p-5 lg:col-span-2">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="label" for="UserFirstName">First name</label>
                            <input wire:model="first_name" type="text" id="UserFirstName" class="{{ $err('first_name') }}" placeholder="John" autocomplete="given-name">
                            @error('first_name') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label" for="UserLastName">Last name</label>
                            <input wire:model="last_name" type="text" id="UserLastName" class="{{ $err('last_name') }}" placeholder="Doe" autocomplete="family-name">
                            @error('last_name') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label" for="UserEmail">E-mail <span class="font-normal text-slate-400">(optional)</span></label>
                            <input wire:model="email" type="email" id="UserEmail" class="{{ $err('email') }}" placeholder="name@domain.com" autocomplete="email">
                            @error('email') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label" for="UserPhone">Phone</label>
                            <input wire:model="phone" type="tel" inputmode="numeric" id="UserPhone" class="{{ $err('phone') }}" placeholder="202 555 0111"
                                x-on:input="$el.value = $el.value.replace(/[^0-9]/g, '')" autocomplete="tel">
                            @error('phone') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>
            </section>

            {{-- Access --}}
            <section class="grid gap-6 lg:grid-cols-3">
                <div>
                    <h2 class="text-base font-semibold">Access</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">The company decides which data they see; the role decides what they can do.</p>
                </div>
                <div class="card p-5 lg:col-span-2">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="label" for="UserCompany">Company</label>
                            <select wire:model.live="company_id" id="UserCompany" class="{{ $selErr('company_id') }}">
                                @if(auth()->user()->canAccessAllCompanies())
                                    <option value="">No company (Super Admin only)</option>
                                @endif
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}">{{ $company->name }}</option>
                                @endforeach
                            </select>
                            @error('company_id') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                        @if(($editing && $user->id != 1) || !$editing)
                            <div>
                                <label class="label" for="UserRole">Role</label>
                                <select wire:model="role_name" id="UserRole" class="{{ $selErr('role_name') }}" wire:key="roles-for-company-{{ $company_id ?: 'none' }}">
                                    <option value="">{{ $company_id || auth()->user()->canAccessAllCompanies() ? 'Select a role' : 'Select a company first' }}</option>
                                    @foreach($this->availableRoles as $role)
                                        <option value="{{ $role->name }}">{{ $role->name }}</option>
                                    @endforeach
                                </select>
                                @error('role_name') <p class="field-error">{{ $message }}</p> @enderror
                            </div>
                        @endif
                        <div class="sm:col-span-2">
                            <label class="label" for="UserName">Username</label>
                            <input wire:model="username" type="text" id="UserName" class="{{ $err('username') }}" placeholder="john.doe" autocomplete="off">
                            @error('username') <p class="field-error">{{ $message }}</p> @enderror
                            <p class="help">Used to sign in.</p>
                        </div>
                    </div>
                </div>
            </section>

            {{-- Password --}}
            @unless($viewOnly)
                <section class="grid gap-6 lg:grid-cols-3">
                    <div>
                        <h2 class="text-base font-semibold">Password</h2>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $editing ? 'Leave blank to keep the current password.' : 'They can sign in with it right away.' }}</p>
                    </div>
                    <div class="card p-5 lg:col-span-2" x-data="{ show: false }">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="label" for="UserPassword">Password</label>
                                <input wire:model="password" :type="show ? 'text' : 'password'" type="password" id="UserPassword" class="{{ $err('password') }}" autocomplete="new-password">
                                @error('password') <p class="field-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="label" for="UserConfirmPassword">Confirm password</label>
                                <input wire:model="password_confirmation" :type="show ? 'text' : 'password'" type="password" id="UserConfirmPassword" class="{{ $err('password_confirmation') }}" autocomplete="new-password">
                                @error('password_confirmation') <p class="field-error">{{ $message }}</p> @enderror
                            </div>
                            <label class="flex items-center gap-2 text-sm text-slate-600 sm:col-span-2 dark:text-slate-400">
                                <input type="checkbox" class="checkbox" x-model="show"> Show passwords
                            </label>
                        </div>
                    </div>
                </section>
            @endunless
        </fieldset>

        @unless($status)
            <div class="sticky bottom-0 -mx-4 flex justify-end gap-2 border-t border-slate-200 bg-slate-50/90 px-4 py-4 backdrop-blur sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8 dark:border-slate-800 dark:bg-slate-950/90">
                <a href="{{ route('users') }}" wire:navigate class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="store,update">
                    <span wire:loading wire:target="store,update" class="size-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                    {{ $editing ? 'Save changes' : 'Create user' }}
                </button>
            </div>
        @endunless
    </form>
</div>
