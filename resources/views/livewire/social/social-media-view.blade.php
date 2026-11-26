@php
    $fmt = fn ($n) => \App\Support\Num::compact($n);
    $platforms = [
        'facebook' => ['label' => 'Facebook', 'icon' => 'brand-facebook', 'dot' => 'bg-[#1877F2]', 'colors' => ['#1877F2', '#0B5FCC', '#5B9BF5']],
        'instagram' => ['label' => 'Instagram', 'icon' => 'brand-instagram', 'dot' => 'bg-[#E1306C]', 'colors' => ['#E1306C', '#A1338F', '#F77737']],
        'ads' => ['label' => 'Meta ad account', 'icon' => 'speakerphone', 'dot' => 'bg-amber-500', 'colors' => ['#F59E0B']],
        'google_ads' => ['label' => 'Google Ads', 'icon' => 'brand-google', 'dot' => 'bg-[#34A853]', 'colors' => ['#34A853', '#188038', '#81C995']],
        'bsb' => ['label' => 'BSB', 'icon' => 'message-2', 'dot' => 'bg-violet-600', 'colors' => ['#7C3AED']],
    ];
@endphp

<div>
    @if($mode === 'picker')
        @include('livewire.social.partials.picker')
    @elseif($mode === 'no-company')
        <x-ui.page-header title="Social Media" />
        <div class="card">
            <x-ui.empty icon="building-off" title="You're not assigned to a company">
                Social accounts belong to a company. Ask an administrator to add you to one.
            </x-ui.empty>
        </div>
    @else
        @include('livewire.social.partials.header')
        @include('livewire.social.partials.alerts')

        @if($accounts->isEmpty())
            @include('livewire.social.partials.connect-empty')
        @else
            {{-- Tabs --}}
            @php
                $tabs = [
                    'overview' => ['Overview', 'chart-area-line', $hasSocial],
                    'posts' => ['Posts', 'photo', $hasSocial],
                    'audience' => ['Audience', 'users-group', $hasSocial],
                    'messages' => ['Messages', 'messages', $hasSocial || $hasWhatsAppChats],
                    'ads' => ['Ads', 'speakerphone', $hasAds],
                    'sms' => ['SMS & WhatsApp', 'message-2', $hasBsb],
                ];
            @endphp
            <div class="mb-6 flex flex-wrap items-end justify-between gap-x-4 gap-y-3 border-b border-slate-200 dark:border-slate-800">
                <nav class="-mb-px flex gap-6 overflow-x-auto scrollbar-none" role="tablist" aria-label="Sections">
                    @foreach($tabs as $key => [$label, $icon, $show])
                        @continue(!$show)
                        <button type="button" role="tab" class="tab" aria-selected="{{ $tab === $key ? 'true' : 'false' }}" wire:click="setTab('{{ $key }}')">
                            <x-icon :name="$icon" class="size-4" /> {{ $label }}
                        </button>
                    @endforeach
                </nav>
                @if(in_array($tab, \App\Livewire\Social\SocialMediaView::PERIOD_TABS))
                    <div class="segmented mb-2" role="group" aria-label="Period">
                        @foreach(\App\Livewire\Social\SocialMediaView::PERIODS as $p)
                            <button type="button" wire:click="setDays({{ $p }})" aria-pressed="{{ $days === $p ? 'true' : 'false' }}">{{ $p }} days</button>
                        @endforeach
                    </div>
                @endif
            </div>

            <div wire:loading.class="opacity-50 pointer-events-none" class="transition-opacity"
                wire:target="setTab,setAccount,setPostPlatform,clearPostFilters,setDays,setTrendMetric,setAudienceMetric,setBreakdown,toggleAdBreakdown,postSort,adSort,gotoPage,nextPage,previousPage,setAdPlatform,setGoogleTrend,setChatFilter,setSmsStatus,setSmsRoute">
                @include('livewire.social.partials.' . $tab)
            </div>
        @endif

        @if($postDetail)
            @include('livewire.social.partials.post-modal')
        @endif

        @if($showFetch)
            @include('livewire.social.partials.fetch-modal')
        @endif

        @if($showBsbConnect)
            @include('livewire.social.partials.bsb-connect-modal')
        @endif

        @if($webhookSetup)
            @include('livewire.social.partials.bsb-webhook-modal', ['acc' => $webhookSetup])
        @endif
    @endif
</div>
