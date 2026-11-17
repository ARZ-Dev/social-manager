<?php

namespace App\Livewire\Social;

use App\Models\Company;
use App\Models\Social\SocialAccount;
use App\Models\Social\SocialAd;
use App\Models\Social\SocialAdBreakdown;
use App\Models\Social\SocialConversation;
use App\Models\Social\SocialDemographic;
use App\Models\Social\SocialInsight;
use App\Models\Social\SocialPost;
use App\Services\Meta\MetaSyncService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Social Media tab: a company's linked Facebook / Instagram / ad accounts and
 * the data fetched from them (posts, comments, insights, audience, messages,
 * boosts and their breakdowns).
 */
class SocialMediaView extends Component
{
    use AuthorizesRequests, WithPagination;

    protected $paginationTheme = 'bootstrap';

    #[Url]
    public $company = '';

    #[Url]
    public string $tab = 'posts';

    #[Url]
    public $account = ''; // filter by one linked account

    public ?int $openPost = null;
    public ?int $openConversation = null;

    // Audience tab: which Instagram audience to show.
    public string $audienceMetric = 'follower_demographics';

    // Boosts tab: breakdown set and, optionally, the one ad it is limited to.
    public string $breakdown = 'age_gender';
    public ?int $breakdownAd = null;

    protected $listeners = ['delete' => 'disconnect'];

    public function mount()
    {
        $this->authorize('social-list');

        $viewer = auth()->user();
        if (!$viewer->canAccessAllCompanies()) {
            $this->company = $viewer->company_id;
        } elseif (!$this->company) {
            $this->company = Company::orderBy('name')->value('id') ?? '';
        }
    }

    public function updatedCompany()
    {
        $this->account = '';
        $this->reset('openPost', 'openConversation', 'breakdownAd');
        $this->resetPage();
    }

    public function updatedAccount()
    {
        $this->reset('breakdownAd');
        $this->resetPage();
    }

    public function setTab($tab)
    {
        $this->tab = in_array($tab, ['posts', 'insights', 'audience', 'messages', 'boosts']) ? $tab : 'posts';
        $this->reset('openPost', 'openConversation', 'breakdownAd');
        $this->resetPage();
    }

    public function setAudienceMetric($metric)
    {
        $metrics = config('meta.demographics.instagram_account.metrics');
        $this->audienceMetric = in_array($metric, $metrics) ? $metric : $metrics[0];
    }

    public function setBreakdown($name)
    {
        $this->breakdown = array_key_exists($name, config('meta.ad_breakdowns')) ? $name : 'age_gender';
    }

    public function toggleAdBreakdown($id)
    {
        $this->breakdownAd = $this->breakdownAd === $id ? null : $id;
    }

    public function togglePost($id)
    {
        $this->openPost = $this->openPost === $id ? null : $id;
    }

    public function openChat($id)
    {
        $this->openConversation = $id;
    }

    protected function currentCompany(): ?Company
    {
        return $this->company
            ? Company::visibleTo(auth()->user())->find($this->company)
            : null;
    }

    protected function accountsQuery()
    {
        return SocialAccount::visibleTo(auth()->user())->where('company_id', $this->company ?: 0);
    }

    /** Account ids the data tabs should read, honoring the account filter. */
    protected function accountIds(?string $platformGroup = null)
    {
        $q = $this->accountsQuery();
        if ($this->account) {
            $q->whereKey($this->account);
        }
        if ($platformGroup === 'social') {
            $q->whereIn('platform', [SocialAccount::FACEBOOK, SocialAccount::INSTAGRAM]);
        } elseif ($platformGroup === 'ads') {
            $q->where('platform', SocialAccount::ADS);
        }

        return $q->pluck('id');
    }

    /**
     * Fetch everything (last N days) for all of this company's accounts.
     * Runs while the user waits; can take a while on large accounts.
     */
    public function fetch()
    {
        $this->authorize('social-sync');
        @set_time_limit(0);

        $accounts = $this->accountsQuery()->with('parent')->get();
        if ($accounts->isEmpty()) {
            $this->dispatch('swal:error', ['title' => 'Nothing to fetch', 'text' => 'Link a Facebook Page first.']);
            return;
        }

        $sync = app(MetaSyncService::class);
        $totals = [];
        $warnings = 0;

        foreach ($accounts as $acc) {
            $result = $sync->syncAccount($acc);
            foreach ($result['counts'] as $k => $v) {
                $totals[$k] = ($totals[$k] ?? 0) + $v;
            }
            $warnings += count($result['warnings']);
        }

        $summary = collect($totals)->map(fn ($v, $k) => "$v $k")->join(', ');
        $text = "Fetched $summary." . ($warnings ? " $warnings warning(s): see the accounts list for details." : '');

        $this->dispatch($warnings ? 'swal:warning' : 'swal:success', [
            'title' => $warnings ? 'Fetched with warnings' : 'Done!',
            'text' => $text,
        ]);
    }

    public function disconnect($id)
    {
        $this->authorize('social-disconnect');

        $acc = $this->accountsQuery()->findOrFail($id);
        // Unlinking a Page also removes its Instagram account (cascade).
        $acc->delete();

        if ($this->account == $id) {
            $this->account = '';
        }

        $this->dispatch('swal:success', ['title' => 'Disconnected', 'text' => 'The account and its fetched data were removed.']);
    }

    public function render()
    {
        $viewer = auth()->user();
        $data = [
            'companies' => $viewer->canAccessAllCompanies() ? Company::orderBy('name')->get() : collect(),
            'currentCompany' => $this->currentCompany(),
            'accounts' => $this->accountsQuery()->orderByRaw("FIELD(platform, 'facebook', 'instagram', 'ads')")->orderBy('name')->get(),
            'metaConfigured' => (bool) $this->currentCompany()?->hasMetaApp(),
        ];

        if ($this->tab === 'posts') {
            $data['posts'] = SocialPost::with('account')
                ->whereIn('social_account_id', $this->accountIds('social'))
                ->latest('posted_at')
                ->paginate(12);
            $data['comments'] = $this->openPost
                ? SocialPost::whereIn('social_account_id', $this->accountIds('social'))
                    ->find($this->openPost)?->comments()->orderBy('commented_at')->get()
                : null;
        }

        if ($this->tab === 'insights') {
            $ids = $this->accountIds('social');
            $data['insightAccounts'] = SocialAccount::whereIn('id', $ids)->get();
            $data['insights'] = SocialInsight::whereIn('social_account_id', $ids)
                ->selectRaw('social_account_id, metric, SUM(value) as total, MAX(date) as last_date, COUNT(*) as days')
                ->groupBy('social_account_id', 'metric')
                ->get()
                ->groupBy('social_account_id');
            $data['topPosts'] = SocialPost::with('account')
                ->whereIn('social_account_id', $ids)
                ->orderByRaw('(like_count + comment_count + share_count) DESC')
                ->limit(10)
                ->get();
        }

        if ($this->tab === 'audience') {
            $ids = $this->accountIds('social');
            $data['audienceAccounts'] = SocialAccount::whereIn('id', $ids)
                ->orderByRaw("FIELD(platform, 'facebook', 'instagram')")->orderBy('name')->get();
            // Instagram rows for the chosen audience; Facebook Pages only have one.
            $data['demographics'] = SocialDemographic::whereIn('social_account_id', $ids)
                ->whereIn('metric', array_merge([$this->audienceMetric], array_keys(config('meta.demographics.facebook_page'))))
                ->orderByDesc('value')
                ->get()
                ->groupBy(['social_account_id', 'dimension']);
        }

        if ($this->tab === 'messages') {
            $data['conversations'] = SocialConversation::with('account')
                ->whereIn('social_account_id', $this->accountIds('social'))
                ->latest('last_message_at')
                ->paginate(20);
            $data['chat'] = $this->openConversation
                ? SocialConversation::whereIn('social_account_id', $this->accountIds('social'))
                    ->with(['messages' => fn ($q) => $q->orderBy('sent_at')])
                    ->find($this->openConversation)
                : null;
        }

        if ($this->tab === 'boosts') {
            $ids = $this->accountIds('ads');
            $data['ads'] = SocialAd::with('account')
                ->whereIn('social_account_id', $ids)
                ->latest('started_at')
                ->paginate(20);
            $data['adTotals'] = SocialAd::whereIn('social_account_id', $ids)
                ->selectRaw('SUM(spend) as spend, SUM(impressions) as impressions, SUM(reach) as reach, SUM(clicks) as clicks')
                ->first();

            $data['breakdownAdModel'] = $this->breakdownAd
                ? SocialAd::whereIn('social_account_id', $ids)->find($this->breakdownAd)
                : null;
            $rows = SocialAdBreakdown::whereIn('social_account_id', $ids)->where('breakdown', $this->breakdown);
            if ($ad = $data['breakdownAdModel']) {
                $rows->where('social_account_id', $ad->social_account_id)->where('ad_external_id', $ad->external_id);
            }
            $rows->selectRaw('segment, SUM(spend) as spend, SUM(impressions) as impressions, SUM(reach) as reach, SUM(clicks) as clicks')
                ->groupBy('segment');
            // Age & gender reads best in age order; the others by spend.
            $this->breakdown === 'age_gender'
                ? $rows->orderBy('segment')
                : $rows->orderByDesc('spend')->orderByDesc('impressions');
            $data['breakdownRows'] = $rows->limit(50)->get();
        }

        return view('livewire.social.social-media-view', $data);
    }
}
