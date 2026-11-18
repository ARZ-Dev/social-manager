<div class="card px-6 py-12 text-center">
    <span class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-300">
        <x-icon name="plug-connected" class="size-7" />
    </span>
    <h2 class="mt-4 text-lg font-semibold">Connect {{ $currentCompany->name }}'s social accounts</h2>
    <p class="mx-auto mt-1 max-w-lg text-sm text-slate-500 dark:text-slate-400">
        Link the company's Facebook Pages (their Instagram accounts come along) and ad accounts to see posts, audience, messages and ad results here.
    </p>

    <ol class="mx-auto mt-8 grid max-w-3xl gap-4 text-left sm:grid-cols-3">
        @foreach([
            ['Connect', 'Log in with Facebook using the company’s Meta app.', 'brand-facebook'],
            ['Choose', 'Pick the Pages and ad accounts this company should read.', 'list-check'],
            ['Fetch', 'Pick the accounts and the period, and the data loads in the background.', 'refresh'],
        ] as $i => [$title, $text, $icon])
            <li class="rounded-xl border border-slate-200 p-4 dark:border-slate-800">
                <div class="flex items-center gap-2">
                    <span class="flex size-6 items-center justify-center rounded-full bg-slate-900 text-xs font-semibold text-white dark:bg-white dark:text-slate-900">{{ $i + 1 }}</span>
                    <span class="text-sm font-semibold text-slate-900 dark:text-white">{{ $title }}</span>
                </div>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ $text }}</p>
            </li>
        @endforeach
    </ol>

    <div class="mt-8">
        @can('social-connect')
            @if($metaConfigured)
                <a href="{{ route('social.connect', ['company' => $currentCompany->id]) }}" class="btn btn-primary">
                    <x-icon name="brand-facebook" class="size-4" /> Connect Facebook &amp; Instagram
                </a>
            @elseif(auth()->user()->can('company-edit'))
                <a href="{{ route('companies.edit', $currentCompany->id) }}#meta-app" wire:navigate class="btn btn-primary">Set up the Meta app first</a>
            @else
                <p class="text-sm text-slate-500 dark:text-slate-400">Ask an administrator to add the company's Meta app.</p>
            @endif
        @endcan
    </div>
</div>
