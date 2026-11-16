<?php

namespace App\Livewire\Social;

use App\Models\Company;
use App\Models\Social\SocialAccount;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * After Facebook Login: choose which Pages, Instagram accounts and ad accounts
 * to link to the company.
 */
class SocialConnectSelect extends Component
{
    use AuthorizesRequests;

    public Company $company;

    /** Display-only lists (no tokens). */
    public array $pages = [];
    public array $adAccounts = [];

    public array $selectedPages = [];
    public array $selectedAds = [];

    public function mount()
    {
        $this->authorize('social-connect');

        $data = $this->sessionData();
        if (!$data) {
            return to_route('social')->with('error', 'Nothing to connect. Please start again.');
        }

        $this->company = Company::visibleTo(auth()->user())->findOrFail($data['company_id']);

        $this->pages = collect($data['pages'])->map(fn ($p) => [
            'id' => $p['id'],
            'name' => $p['name'] ?? $p['id'],
            'picture' => $p['picture']['data']['url'] ?? null,
            'instagram' => isset($p['instagram_business_account']) ? [
                'username' => $p['instagram_business_account']['username'] ?? null,
                'picture' => $p['instagram_business_account']['profile_picture_url'] ?? null,
            ] : null,
        ])->all();

        $this->adAccounts = collect($data['ad_accounts'])->map(fn ($a) => [
            'id' => $a['id'],
            'name' => $a['name'] ?? $a['id'],
            'currency' => $a['currency'] ?? null,
            'active' => ($a['account_status'] ?? 1) == 1,
        ])->all();

        $this->selectedPages = collect($this->pages)->pluck('id')->all();
    }

    protected function sessionData(): ?array
    {
        $raw = session('meta_connect');
        try {
            return $raw ? decrypt($raw) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function save()
    {
        $this->authorize('social-connect');

        $data = $this->sessionData();
        if (!$data || $data['company_id'] != $this->company->id) {
            return to_route('social')->with('error', 'The connection session expired. Please start again.');
        }

        if (!$this->selectedPages && !$this->selectedAds) {
            $this->addError('selection', 'Select at least one Page or ad account.');
            return;
        }

        $userTokenExpires = $data['expires_in'] ? now()->addSeconds($data['expires_in']) : null;
        $count = 0;

        DB::transaction(function () use ($data, $userTokenExpires, &$count) {
            foreach ($data['pages'] as $page) {
                if (!in_array($page['id'], $this->selectedPages)) {
                    continue;
                }

                // Page tokens from a long-lived user token don't expire.
                $fb = SocialAccount::updateOrCreate(
                    ['company_id' => $this->company->id, 'platform' => SocialAccount::FACEBOOK, 'external_id' => $page['id']],
                    [
                        'name' => $page['name'] ?? null,
                        'picture_url' => $page['picture']['data']['url'] ?? null,
                        'access_token' => $page['access_token'],
                        'token_expires_at' => null,
                        'connected_by' => auth()->id(),
                        'last_sync_error' => null,
                    ]
                );
                $count++;

                if ($ig = $page['instagram_business_account'] ?? null) {
                    SocialAccount::updateOrCreate(
                        ['company_id' => $this->company->id, 'platform' => SocialAccount::INSTAGRAM, 'external_id' => $ig['id']],
                        [
                            'parent_id' => $fb->id,
                            'name' => $ig['name'] ?? null,
                            'username' => $ig['username'] ?? null,
                            'picture_url' => $ig['profile_picture_url'] ?? null,
                            'access_token' => $page['access_token'],
                            'token_expires_at' => null,
                            'connected_by' => auth()->id(),
                            'last_sync_error' => null,
                        ]
                    );
                    $count++;
                }
            }

            foreach ($data['ad_accounts'] as $ad) {
                if (!in_array($ad['id'], $this->selectedAds)) {
                    continue;
                }

                // Ads API uses the user token (expires after ~60 days; reconnect to renew).
                SocialAccount::updateOrCreate(
                    ['company_id' => $this->company->id, 'platform' => SocialAccount::ADS, 'external_id' => $ad['id']],
                    [
                        'name' => $ad['name'] ?? null,
                        'access_token' => $data['user_token'],
                        'token_expires_at' => $userTokenExpires,
                        'meta' => ['currency' => $ad['currency'] ?? null],
                        'connected_by' => auth()->id(),
                        'last_sync_error' => null,
                    ]
                );
                $count++;
            }
        });

        session()->forget('meta_connect');

        return to_route('social', ['company' => $this->company->id])
            ->with('success', "Linked $count account(s). Click Fetch data to load posts.");
    }

    public function cancel()
    {
        session()->forget('meta_connect');
        return to_route('social', ['company' => $this->company->id]);
    }

    public function render()
    {
        return view('livewire.social.social-connect-select');
    }
}
