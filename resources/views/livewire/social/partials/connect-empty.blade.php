<div class="card px-6 py-12 text-center">
    <span class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-300">
        <x-icon name="plug-connected" class="size-7" />
    </span>
    <h2 class="mt-4 text-lg font-semibold">Connect {{ $currentCompany->name }}'s accounts</h2>
    <p class="mx-auto mt-1 max-w-lg text-sm text-slate-500 dark:text-slate-400">
        Link the platforms the company uses. Then pick the accounts and the period with <strong>Fetch data</strong>, and the data loads in the background.
    </p>

    @can('social-connect')
        @include('livewire.social.partials.connect-options', ['menu' => false])
    @else
        <p class="mt-6 text-sm text-slate-500 dark:text-slate-400">Ask an administrator to connect the company's accounts.</p>
    @endcan
</div>
