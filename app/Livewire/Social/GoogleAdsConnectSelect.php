<?php

namespace App\Livewire\Social;

use App\Models\Company;
use App\Models\Social\SocialAccount;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * After Google sign-in: choose which Google Ads accounts to link to the company.
 */
class GoogleAdsConnectSelect extends Component
{
    use AuthorizesRequests;

    public Company $company;

    /** Display-only list (no tokens). */
    public array $accounts = [];

    /** Accounts Google refused to list, with the reason. */
    public array $skipped = [];

    public array $selected = [];

    public function mount()
    {
        $this->authorize('social-connect');

        $data = $this->sessionData();
        if (!$data) {
            return to_route('social')->with('error', 'Nothing to connect. Please start again.');
        }

        $this->company = Company::visibleTo(auth()->user())->findOrFail($data['company_id']);
        $this->accounts = $data['accounts'];
        $this->skipped = $data['skipped'] ?? [];

        // Already linked accounts and active ones start ticked.
        $linked = SocialAccount::where('company_id', $this->company->id)
            ->where('platform', SocialAccount::GOOGLE_ADS)
            ->pluck('external_id')
            ->all();
        $this->selected = collect($this->accounts)
            ->filter(fn ($a) => in_array($a['id'], $linked) || (count($this->accounts) <= 5 && $a['active']))
            ->pluck('id')
            ->all();
    }

    protected function sessionData(): ?array
    {
        $raw = session('google_ads_connect');
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

        if (!$this->selected) {
            $this->addError('selected', 'Select at least one Google Ads account.');
            return;
        }

        $count = 0;
        DB::transaction(function () use ($data, &$count) {
            foreach ($data['accounts'] as $acc) {
                if (!in_array($acc['id'], $this->selected)) {
                    continue;
                }

                $existing = SocialAccount::where('company_id', $this->company->id)
                    ->where('platform', SocialAccount::GOOGLE_ADS)
                    ->where('external_id', $acc['id'])
                    ->first();

                // Refresh tokens don't expire while the app's OAuth consent screen is
                // "In production" (in "Testing", Google drops them after 7 days).
                SocialAccount::updateOrCreate(
                    ['company_id' => $this->company->id, 'platform' => SocialAccount::GOOGLE_ADS, 'external_id' => $acc['id']],
                    [
                        'name' => $acc['name'],
                        'access_token' => $data['refresh_token'],
                        'token_expires_at' => null,
                        'meta' => array_merge($existing?->meta ?? [], [
                            'currency' => $acc['currency'],
                            'time_zone' => $acc['time_zone'],
                            'login_customer_id' => $acc['login_customer_id'],
                            'manager_name' => $acc['manager_name'],
                            'test_account' => $acc['test'],
                        ]),
                        'connected_by' => auth()->id(),
                        'last_sync_error' => null,
                    ]
                );
                $count++;
            }
        });

        session()->forget('google_ads_connect');

        return to_route('social', ['company' => $this->company->id, 'tab' => 'ads'])
            ->with('success', "Linked $count Google Ads " . str('account')->plural($count) . '. Click Fetch data to load campaigns.');
    }

    public function cancel()
    {
        session()->forget('google_ads_connect');
        return to_route('social', ['company' => $this->company->id]);
    }

    public function render()
    {
        return view('livewire.social.google-ads-connect-select');
    }
}
