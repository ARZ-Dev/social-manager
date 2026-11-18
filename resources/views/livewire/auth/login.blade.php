<div class="flex min-h-full items-center justify-center px-4 py-12">
    <div class="w-full max-w-sm">
        <div class="mb-8 flex justify-center">
            <img src="{{ asset('farm_assets/images/farm_images/logo.png') }}" alt="SocialM" class="h-12 w-auto object-contain dark:brightness-0 dark:invert">
        </div>

        <div class="card p-6 sm:p-8">
            <h1 class="text-xl font-semibold">Sign in</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Welcome back. Enter your details to continue.</p>

            <form wire:submit="login" class="mt-6 space-y-4" novalidate>
                <div>
                    <label for="username" class="label">Username</label>
                    <input wire:model="username" type="text" id="username" autocomplete="username" autofocus required
                        @class(['input', 'input-error' => $errors->has('username')])
                        aria-invalid="{{ $errors->has('username') ? 'true' : 'false' }}" @error('username') aria-describedby="login-error" @enderror>
                </div>

                <div x-data="{ show: false }">
                    <label for="password" class="label">Password</label>
                    <div class="relative">
                        <input wire:model="password" :type="show ? 'text' : 'password'" type="password" id="password" autocomplete="current-password" required
                            @class(['input pr-10', 'input-error' => $errors->has('password')])>
                        <button type="button" class="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                            x-on:click="show = !show" :aria-label="show ? 'Hide password' : 'Show password'">
                            <span x-show="!show"><x-icon name="eye" class="size-4" /></span>
                            <span x-show="show" x-cloak><x-icon name="eye-off" class="size-4" /></span>
                        </button>
                    </div>
                    @error('password') <p class="field-error">{{ $message }}</p> @enderror
                </div>

                <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400">
                    <input wire:model="rememberMe" type="checkbox" class="checkbox"> Remember me
                </label>

                @error('username')
                    <div id="login-error" class="alert alert-danger py-3" role="alert">
                        <x-icon name="alert-circle" class="size-5 shrink-0" />
                        <span>{{ $message === trans('auth.failed') ? 'Wrong username or password.' : $message }}</span>
                    </div>
                @enderror

                <button type="submit" class="btn btn-primary w-full" wire:loading.attr="disabled" wire:target="login">
                    <span wire:loading wire:target="login" class="size-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                    Sign in
                </button>
            </form>
        </div>
    </div>
</div>
