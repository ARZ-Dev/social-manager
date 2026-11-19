{{-- Ads tab: Meta ad accounts or Google Ads, with a switch when the company has both --}}
@if($hasMetaAds && $hasGoogleAds)
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div class="segmented" role="group" aria-label="Ad platform">
            <button type="button" wire:click="setAdPlatform('meta')" aria-pressed="{{ $adPlatform === 'meta' ? 'true' : 'false' }}">
                <x-icon name="brand-meta" class="size-4" /> Meta ads
            </button>
            <button type="button" wire:click="setAdPlatform('google')" aria-pressed="{{ $adPlatform === 'google' ? 'true' : 'false' }}">
                <x-icon name="brand-google" class="size-4" /> Google Ads
            </button>
        </div>
        <p class="text-xs text-slate-500 dark:text-slate-400">Each platform counts results its own way, so they're shown apart.</p>
    </div>
@endif

@include('livewire.social.partials.' . ($adPlatform === 'google' ? 'ads-google' : 'ads-meta'))
