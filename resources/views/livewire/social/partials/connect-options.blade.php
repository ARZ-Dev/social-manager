{{-- The platforms a company can connect. $menu: dropdown items (header) or cards (empty state). --}}
@php
    $canEditCompany = auth()->user()->can('company-edit');
    $options = [
        [
            'title' => 'Facebook & Instagram',
            'text' => 'Pages, their Instagram accounts, Meta ad accounts. Posts, audience, messages and boosts.',
            'icon' => 'brand-meta', 'tone' => 'text-[#1877F2]',
            'ready' => $metaConfigured,
            'href' => route('social.connect', ['company' => $currentCompany->id]),
            'setup' => route('companies.edit', $currentCompany->id) . '#meta-app',
            'missing' => 'Needs the company\'s Meta app',
        ],
        [
            'title' => 'Google Ads',
            'text' => 'Campaign performance, engagement, conversions and audience.',
            'icon' => 'brand-google', 'tone' => 'text-[#34A853]',
            'ready' => $googleConfigured,
            'href' => route('social.google.connect', ['company' => $currentCompany->id]),
            'setup' => route('companies.edit', $currentCompany->id) . '#google-ads',
            'missing' => 'Needs the company\'s Google Ads developer token',
        ],
        [
            'title' => 'BSB (SMS & WhatsApp)',
            'text' => 'Messages sent through bestsmsbulk.com, their delivery and the balance.',
            'icon' => 'message-2', 'tone' => 'text-violet-600 dark:text-violet-400',
            'ready' => true,
            'action' => 'openBsbConnect',
        ],
    ];
@endphp

@if($menu ?? false)
    @foreach($options as $o)
        @php $inner = '<span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 dark:bg-slate-800">' . \App\Support\Icon::svg($o['icon'], 'size-4 ' . $o['tone']) . '</span>'; @endphp
        @if(!empty($o['action']))
            <button type="button" class="dropdown-item items-start gap-3 py-2" role="menuitem" wire:click="{{ $o['action'] }}" x-on:click="open = false">
                {!! $inner !!}
                <span class="min-w-0 text-left">
                    <span class="block text-sm font-medium text-slate-900 dark:text-white">{{ $o['title'] }}</span>
                    <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $o['text'] }}</span>
                </span>
            </button>
        @elseif($o['ready'])
            <a href="{{ $o['href'] }}" class="dropdown-item items-start gap-3 py-2" role="menuitem">
                {!! $inner !!}
                <span class="min-w-0">
                    <span class="block text-sm font-medium text-slate-900 dark:text-white">{{ $o['title'] }}</span>
                    <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $o['text'] }}</span>
                </span>
            </a>
        @else
            <div class="flex items-start gap-3 rounded-lg px-3 py-2 opacity-90" role="menuitem" aria-disabled="true">
                {!! $inner !!}
                <span class="min-w-0">
                    <span class="block text-sm font-medium text-slate-900 dark:text-white">{{ $o['title'] }}</span>
                    <span class="block text-xs text-amber-700 dark:text-amber-400">{{ $o['missing'] }}.</span>
                    @if($canEditCompany)
                        <a href="{{ $o['setup'] }}" wire:navigate class="link text-xs">Set it up</a>
                    @endif
                </span>
            </div>
        @endif
    @endforeach
@else
    <div class="mx-auto mt-8 grid max-w-4xl gap-4 text-left sm:grid-cols-3">
        @foreach($options as $o)
            <div class="flex flex-col rounded-xl border border-slate-200 p-4 dark:border-slate-800">
                <span class="flex size-10 items-center justify-center rounded-xl bg-slate-100 dark:bg-slate-800">
                    <x-icon :name="$o['icon']" class="size-5 {{ $o['tone'] }}" />
                </span>
                <p class="mt-3 text-sm font-semibold text-slate-900 dark:text-white">{{ $o['title'] }}</p>
                <p class="mt-1 flex-1 text-sm text-slate-500 dark:text-slate-400">{{ $o['text'] }}</p>
                <div class="mt-4">
                    @if(!empty($o['action']))
                        <button type="button" class="btn btn-primary btn-sm w-full" wire:click="{{ $o['action'] }}">Connect</button>
                    @elseif($o['ready'])
                        <a href="{{ $o['href'] }}" class="btn btn-primary btn-sm w-full">Connect</a>
                    @elseif($canEditCompany)
                        <a href="{{ $o['setup'] }}" wire:navigate class="btn btn-secondary btn-sm w-full">Set up first</a>
                    @else
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ $o['missing'] }}. Ask an administrator.</p>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
@endif
