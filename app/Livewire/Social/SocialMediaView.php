<?php

namespace App\Livewire\Social;

use App\Jobs\SyncSocialAccounts;
use App\Models\Company;
use App\Models\Social\SocialAccount;
use App\Models\Social\SocialAd;
use App\Models\Social\SocialAdBreakdown;
use App\Models\Social\SocialAdDailyStat;
use App\Models\Social\SocialBulkMessage;
use App\Models\Social\SocialConversation;
use App\Models\Social\SocialDemographic;
use App\Models\Social\SocialInsight;
use App\Models\Social\SocialMessage;
use App\Models\Social\SocialPost;
use App\Services\Bsb\BsbClient;
use App\Services\Bsb\BsbException;
use App\Support\Num;
use App\Support\ResponseTimes;
use Carbon\CarbonPeriod;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Social Media page. Super Admins first pick a company; everyone else sees
 * their own. Shows the company's linked accounts (Facebook, Instagram, Meta and
 * Google ad accounts, BSB) and the data fetched from them: overview, posts,
 * audience, messages with response times, ads, and SMS / WhatsApp delivery.
 */
class SocialMediaView extends Component
{
    use AuthorizesRequests, WithPagination;

    public const TABS = ['overview', 'posts', 'audience', 'messages', 'ads', 'sms'];
    public const PERIODS = [7, 30, 90];

    /** Tabs that show a 7 / 30 / 90 days switch. */
    public const PERIOD_TABS = ['overview', 'messages', 'sms'];

    /** Google Ads daily chart: metric => label. */
    public const GOOGLE_TREND_METRICS = ['spend' => 'Cost', 'clicks' => 'Clicks', 'impressions' => 'Impressions', 'conversions' => 'Conversions'];

    /** A fetch with no sign of life for this long is treated as dead (e.g. the server restarted). */
    public const SYNC_STALE_MINUTES = 10;

    #[Url]
    public $company = '';

    #[Url]
    public string $tab = 'overview';

    #[Url]
    public $account = ''; // limit everything to one linked account

    #[Url]
    public int $days = 30; // Overview period

    #[Url(as: 'metric')]
    public string $trendMetric = '';

    #[Url(as: 'q')]
    public string $postSearch = '';

    #[Url(as: 'sort')]
    public string $postSort = 'newest';

    #[Url(as: 'platform')]
    public string $postPlatform = ''; // Posts tab: '' | facebook | instagram

    #[Url(as: 'ads')]
    public string $adPlatform = ''; // Ads tab: meta | google ('' = the first the company has)

    #[Url(as: 'status')]
    public string $smsStatus = ''; // SMS tab: '' | delivered | progress | failed

    #[Url(as: 'route')]
    public string $smsRoute = ''; // SMS tab: '' | sms | wp

    public string $smsSearch = '';
    public string $companySearch = '';
    public string $chatSearch = '';
    public string $chatFilter = ''; // Messages tab: '' | awaiting
    public string $audienceMetric = 'follower_demographics';
    public string $breakdown = 'age_gender';
    public string $adSort = 'spend';
    public string $googleTrend = 'spend';

    // "Connect BSB" dialog
    public bool $showBsbConnect = false;
    public string $bsbName = '';
    public string $bsbKey = '';
    public string $bsbSecret = '';

    public ?int $openPost = null;
    public ?int $openConversation = null;
    public ?int $breakdownAd = null;

    /** A background fetch is running for this company; the page polls checkSync(). */
    public bool $syncing = false;

    // Fetch dialog: which accounts and which period.
    public bool $showFetch = false;
    public array $fetchAccounts = [];
    public string $fetchPeriod = '90';
    public string $fetchFrom = '';

    public function mount()
    {
        $this->authorize('social-list');

        $viewer = auth()->user();
        if (!$viewer->canAccessAllCompanies()) {
            $this->company = $viewer->company_id ?? '';
        } elseif ($this->company && !Company::visibleTo($viewer)->whereKey($this->company)->exists()) {
            $this->company = '';
        }

        // Older links used "insights" and "boosts".
        $this->tab = match ($this->tab) {
            'insights' => 'overview',
            'boosts' => 'ads',
            default => in_array($this->tab, self::TABS) ? $this->tab : 'overview',
        };
        $this->days = in_array($this->days, self::PERIODS) ? $this->days : 30;
        $this->syncing = $this->company && $this->runningSync()->exists();
    }

    // ------------------------------------------------------------ Navigation

    public function selectCompany($id)
    {
        abort_unless(auth()->user()->canAccessAllCompanies(), 403);

        $this->company = Company::visibleTo(auth()->user())->findOrFail($id)->id;
        $this->reset('account', 'trendMetric', 'postSearch', 'chatSearch', 'companySearch', 'openPost', 'openConversation', 'breakdownAd',
            'adPlatform', 'chatFilter', 'smsStatus', 'smsRoute', 'smsSearch');
        $this->syncing = $this->runningSync()->exists();
        $this->resetPage();
    }

    /** Back to the company picker. */
    public function clearCompany()
    {
        abort_unless(auth()->user()->canAccessAllCompanies(), 403);

        $this->reset('company', 'account', 'trendMetric', 'postSearch', 'chatSearch', 'openPost', 'openConversation', 'breakdownAd', 'syncing',
            'adPlatform', 'chatFilter', 'smsStatus', 'smsRoute', 'smsSearch');
        $this->tab = 'overview';
        $this->resetPage();
    }

    public function setTab($tab)
    {
        $this->tab = in_array($tab, self::TABS) ? $tab : 'overview';
        $this->reset('openPost', 'openConversation', 'breakdownAd');
        $this->resetPage();
    }

    public function setAccount($id = '')
    {
        $acc = $id ? $this->accountsQuery()->find($id) : null;
        $this->account = $acc?->id ?? '';
        $this->reset('trendMetric', 'openPost', 'openConversation', 'breakdownAd');
        $this->resetPage();

        // A platform filter that excludes the chosen account no longer makes sense.
        if ($acc && $this->postPlatform && $acc->platform !== $this->postPlatform) {
            $this->postPlatform = '';
        }

        // Jump to a tab that has data for this kind of account.
        if (in_array($acc?->platform, SocialAccount::AD_PLATFORMS, true)) {
            $this->tab = 'ads';
            $this->adPlatform = $acc->platform === SocialAccount::GOOGLE_ADS ? 'google' : 'meta';
        } elseif ($acc?->platform === SocialAccount::BSB) {
            $this->tab = 'sms';
        } elseif ($acc && in_array($this->tab, ['ads', 'sms'], true)) {
            $this->tab = 'overview';
        }
        $this->syncBreakdownChoice();
    }

    /** Ads tab: Meta or Google ad accounts. */
    public function setAdPlatform($platform)
    {
        $this->adPlatform = in_array($platform, ['meta', 'google'], true) ? $platform : '';
        $this->reset('breakdownAd');
        $this->resetPage();

        // Drop an account filter that belongs to the other platform.
        $wanted = $this->adPlatform === 'google' ? SocialAccount::GOOGLE_ADS : SocialAccount::ADS;
        if ($this->account && $this->accountsQuery()->whereKey($this->account)->value('platform') !== $wanted) {
            $this->account = '';
        }
        $this->syncBreakdownChoice();
    }

    /** Keep the breakdown on one the current ad platform offers. */
    protected function syncBreakdownChoice(): void
    {
        $options = $this->adPlatform === 'google' ? config('google_ads.breakdowns') : config('meta.ad_breakdowns');
        if (!array_key_exists($this->breakdown, $options)) {
            $this->breakdown = array_key_first($options);
        }
    }

    public function setGoogleTrend($metric)
    {
        $this->googleTrend = array_key_exists($metric, self::GOOGLE_TREND_METRICS) ? $metric : 'spend';
    }

    /** Messages tab: '' = all conversations, 'awaiting' = the customer is waiting for a reply. */
    public function setChatFilter($filter = '')
    {
        $this->chatFilter = $filter === 'awaiting' ? 'awaiting' : '';
        $this->resetPage();
    }

    /** SMS tab filters. */
    public function setSmsStatus($status = '')
    {
        $this->smsStatus = in_array($status, ['delivered', 'progress', 'failed'], true) ? $status : '';
        $this->resetPage();
    }

    public function setSmsRoute($route = '')
    {
        $this->smsRoute = array_key_exists($route, SocialBulkMessage::ROUTES) ? $route : '';
        $this->resetPage();
    }

    public function updatedSmsSearch()
    {
        $this->resetPage();
    }

    public function setDays($days)
    {
        $this->days = in_array((int) $days, self::PERIODS) ? (int) $days : 30;
    }

    public function setTrendMetric($key)
    {
        $this->trendMetric = (string) $key;
    }

    public function setAudienceMetric($metric)
    {
        $metrics = config('meta.demographics.instagram_account.metrics');
        $this->audienceMetric = in_array($metric, $metrics) ? $metric : $metrics[0];
    }

    public function setBreakdown($name)
    {
        $this->breakdown = (string) $name;
        $this->syncBreakdownChoice();
    }

    public function toggleAdBreakdown($id)
    {
        $this->breakdownAd = $this->breakdownAd === (int) $id ? null : (int) $id;
    }

    public function openPostDetail($id)
    {
        $this->openPost = (int) $id;
    }

    public function closePost()
    {
        $this->openPost = null;
    }

    public function openChat($id)
    {
        $this->openConversation = (int) $id;
    }

    public function closeChat()
    {
        $this->openConversation = null;
    }

    public function updatedPostSearch()
    {
        $this->resetPage();
    }

    public function updatedPostSort()
    {
        $this->resetPage();
    }

    /** Posts tab: show one platform only ('' = all). */
    public function setPostPlatform($platform = '')
    {
        $this->postPlatform = in_array($platform, [SocialAccount::FACEBOOK, SocialAccount::INSTAGRAM], true) ? $platform : '';
        $this->reset('openPost');
        $this->resetPage();

        // Drop an account filter that belongs to the other platform.
        if ($this->postPlatform && $this->account
            && $this->accountsQuery()->whereKey($this->account)->value('platform') !== $this->postPlatform) {
            $this->account = '';
        }
    }

    /** Posts tab: clear search, platform and account filters. */
    public function clearPostFilters()
    {
        $this->reset('postSearch', 'postPlatform', 'account', 'openPost');
        $this->resetPage();
    }

    public function updatedChatSearch()
    {
        $this->resetPage();
    }

    public function updatedAdSort()
    {
        $this->resetPage();
    }

    // --------------------------------------------------------------- Actions

    /** Open the Fetch dialog with every account ticked and the default period. */
    public function openFetch()
    {
        $this->authorize('social-sync');

        if ($this->runningSync()->exists()) {
            $this->syncing = true;
            $this->dispatch('swal:info', ['title' => 'Already fetching', 'text' => 'Stop the running fetch first, or wait for it to finish.']);
            return;
        }

        $ids = $this->orderedAccountIds();
        if ($ids->isEmpty()) {
            $this->dispatch('swal:error', ['title' => 'Nothing to fetch', 'text' => 'Connect an account first.']);
            return;
        }

        $this->fetchAccounts = $ids->map(fn ($id) => (string) $id)->all();
        $default = (string) config('meta.history_days');
        $this->fetchPeriod = array_key_exists($default, SocialAccount::FETCH_PERIODS) ? $default : '90';
        $this->fetchFrom = now()->subDays(30)->toDateString();
        $this->resetValidation();
        $this->showFetch = true;
    }

    public function closeFetch()
    {
        $this->showFetch = false;
    }

    /**
     * Tick every account (of one platform, or all), or untick them if they all are.
     */
    public function toggleFetchGroup(?string $platform = null)
    {
        $group = $this->accountsQuery()
            ->when($platform, fn ($q) => $q->where('platform', $platform))
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        $allOn = count(array_intersect($group, $this->fetchAccounts)) === count($group);
        $this->fetchAccounts = $allOn
            ? array_values(array_diff($this->fetchAccounts, $group))
            : array_values(array_unique(array_merge($this->fetchAccounts, $group)));
    }

    /**
     * Start fetching the ticked accounts for the chosen period. The work runs
     * after this request has answered (see SyncSocialAccounts), so a long fetch
     * can't time out in the browser; the page polls checkSync() for progress.
     */
    public function startFetch()
    {
        $this->authorize('social-sync');

        $all = $this->orderedAccountIds();
        $this->validate([
            'fetchAccounts' => ['required', 'array', 'min:1'],
            'fetchAccounts.*' => [Rule::in($all->map(fn ($id) => (string) $id)->all())],
            'fetchPeriod' => ['required', Rule::in(array_keys(SocialAccount::FETCH_PERIODS))],
            'fetchFrom' => ['exclude_unless:fetchPeriod,custom', 'required', 'date', 'before_or_equal:today',
                'after_or_equal:' . now()->subDays(SocialAccount::FETCH_MAX_DAYS)->toDateString()],
        ], [
            'fetchAccounts.required' => 'Pick at least one account.',
            'fetchAccounts.min' => 'Pick at least one account.',
            'fetchFrom.after_or_equal' => 'Meta keeps about 2 years of data: pick a date after :date.',
        ], ['fetchFrom' => 'start date']);

        if ($this->runningSync()->exists()) {
            $this->showFetch = false;
            $this->syncing = true;
            return;
        }

        $since = $this->fetchPeriod === 'custom'
            ? Carbon::parse($this->fetchFrom)->startOfDay()
            : now()->subDays((int) $this->fetchPeriod)->startOfDay();
        // Keep Pages before their Instagram accounts and ad accounts.
        $this->queueFetch($all->filter(fn ($id) => in_array((string) $id, $this->fetchAccounts, true))->values(), $since);

        $this->showFetch = false;
    }

    /** Fetch these accounts in the background, right after this request answers. */
    protected function queueFetch(Collection $ids, Carbon $since): void
    {
        $runId = (string) Str::uuid();

        SocialAccount::whereKey($ids)->update(['sync_queued_at' => now(), 'sync_run_id' => $runId, 'sync_state' => 'queued', 'sync_progress' => null, 'sync_step' => null]);
        SyncSocialAccounts::dispatchAfterResponse($ids->all(), $runId, $since->toDateString());

        $this->syncing = true;
    }

    // ------------------------------------------------------------ Connect BSB

    public function openBsbConnect()
    {
        $this->authorize('social-connect');
        $this->reset('bsbName', 'bsbKey', 'bsbSecret');
        $this->resetValidation();
        $this->showBsbConnect = true;
    }

    public function closeBsbConnect()
    {
        $this->showBsbConnect = false;
        $this->reset('bsbKey', 'bsbSecret');
    }

    /**
     * Link a BSB account with its API key and secret (from BSB's MANAGE APIs page).
     * The credentials are checked by reading the balance, then today's messages
     * are fetched straight away.
     */
    public function connectBsb()
    {
        $this->authorize('social-connect');
        $company = $this->currentCompany();
        abort_unless($company, 404);

        $this->bsbKey = trim($this->bsbKey);
        $this->bsbSecret = trim($this->bsbSecret);
        $this->validate([
            'bsbName' => ['nullable', 'string', 'max:100'],
            'bsbKey' => ['required', 'string', 'max:255'],
            'bsbSecret' => ['required', 'string', 'max:255'],
        ], [], ['bsbName' => 'name', 'bsbKey' => 'API key', 'bsbSecret' => 'API secret']);

        try {
            $balance = (new BsbClient($this->bsbKey, $this->bsbSecret))->balance();
        } catch (BsbException $e) {
            $this->addError('bsbKey', 'BSB refused these credentials: ' . $e->getMessage());
            return;
        }

        $fingerprint = SocialAccount::bsbFingerprint($this->bsbKey);
        $existing = SocialAccount::where('company_id', $company->id)->where('platform', SocialAccount::BSB)->where('external_id', $fingerprint)->first();

        $account = SocialAccount::updateOrCreate(
            ['company_id' => $company->id, 'platform' => SocialAccount::BSB, 'external_id' => $fingerprint],
            [
                'name' => trim($this->bsbName) ?: ($existing?->name ?? 'BSB'),
                'access_token' => json_encode(['api_key' => $this->bsbKey, 'api_secret' => $this->bsbSecret]),
                'meta' => array_merge($existing?->meta ?? [], [
                    'sms_balance' => $balance['sms'],
                    'wp_balance' => $balance['wp'],
                    'balance_at' => now()->toIso8601String(),
                ]),
                'connected_by' => auth()->id(),
                'last_sync_error' => null,
            ]
        );

        $this->closeBsbConnect();
        $this->tab = 'sms';
        $this->account = '';
        $this->resetPage();

        if (auth()->user()->can('social-sync') && !$this->runningSync()->exists()) {
            $this->queueFetch(collect([$account->id]), now()->startOfDay());
        }

        $this->dispatch('swal:success', [
            'title' => $existing ? 'BSB account updated' : 'BSB account connected',
            'text' => 'Messages sent today are being loaded. From now on new messages are picked up every ' . config('bsb.poll_minutes') . ' minutes.',
        ]);
    }

    /** Stop the running fetch. What was saved so far is kept. */
    public function stopFetch()
    {
        $this->authorize('social-sync');

        // The run id stays so the accounts keep showing in the run's list; the job sees "stopped".
        $this->runningSync()->update(['sync_queued_at' => null, 'sync_state' => 'stopped']);
        $this->syncing = false;
        $this->dispatch('swal:info', [
            'title' => 'Fetch stopped',
            'text' => 'Data fetched so far is kept. The account in progress stops after its current step.',
        ]);
    }

    protected function orderedAccountIds(): Collection
    {
        return $this->accountsQuery()
            ->orderedByPlatform()
            ->orderBy('id')
            ->pluck('id');
    }

    /**
     * Polled every few seconds while a fetch runs. Returns how many accounts are
     * left and each account's state; the page only re-renders once all are done.
     *
     * @return array{left: int, states: array<int, array{state: string, note: string, warn: bool}>}
     */
    public function checkSync(): array
    {
        $runId = $this->currentRunId();
        $left = $this->runningSync()->count();
        if ($left > 0) {
            $this->skipRender();
            return ['left' => $left, 'states' => $this->runStates($runId)];
        }

        if ($this->syncing) {
            $this->syncing = false;
            $warned = $this->accountsQuery()->whereNotNull('last_sync_error')->count();
            $this->dispatch($warned ? 'swal:warning' : 'swal:success', [
                'title' => $warned ? 'Fetched, with warnings' : 'Data updated',
                'text' => $warned
                    ? "Part of the fetch was refused on $warned " . str('account')->plural($warned) . '. The warnings panel lists what.'
                    : 'All accounts are up to date.',
            ]);
        }

        return ['left' => 0, 'states' => []];
    }

    /** The run the company's waiting/running accounts belong to. */
    protected function currentRunId(): ?string
    {
        return $this->runningSync()->value('sync_run_id');
    }

    /** Accounts of a fetch run, in the order they are fetched. */
    protected function runAccounts(?string $runId): Collection
    {
        if (!$runId) {
            return collect();
        }

        return $this->accountsQuery()
            ->where('sync_run_id', $runId)
            ->orderedByPlatform()
            ->orderBy('id')
            ->get();
    }

    /**
     * State of every account of a run for the progress list, e.g.
     * [12 => ['state' => 'running', 'note' => 'Posts and comments', 'warn' => false, 'progress' => 42]].
     * progress: 0-100 while running, 100 once finished (whatever the outcome), 0 while waiting.
     */
    protected function runStates(?string $runId): array
    {
        return $this->runAccounts($runId)->mapWithKeys(fn ($a) => [$a->id => [
            'state' => $a->sync_state ?? 'queued',
            'note' => $this->runNote($a),
            'warn' => $a->sync_state === 'done' && filled($a->last_sync_error),
            'progress' => match ($a->sync_state) {
                'done', 'failed', 'stopped' => 100,
                'running' => (int) $a->sync_progress,
                default => 0,
            },
        ]])->all();
    }

    protected function runNote(SocialAccount $a): string
    {
        return match ($a->sync_state) {
            'done' => collect(['posts', 'comments', 'conversations', 'ads', 'campaigns', 'new messages', 'status updates'])
                ->filter(fn ($k) => ($a->meta['last_fetch_counts'][$k] ?? 0) > 0)
                ->map(fn ($k) => number_format($a->meta['last_fetch_counts'][$k]) . ' ' . ($a->meta['last_fetch_counts'][$k] == 1 ? Str::singular($k) : $k))
                ->join(' · ') ?: 'Up to date',
            'failed' => Str::limit(Str::after((string) $a->last_sync_error, 'The fetch stopped: '), 80),
            'stopped' => 'Stopped before it finished',
            // In progress: the step it's on.
            'running' => $a->sync_step ?: 'Starting…',
            // Waiting: say what kind of account it is.
            default => [
                'facebook' => 'Facebook Page', 'instagram' => 'Instagram account', 'ads' => 'Meta ad account',
                'google_ads' => 'Google Ads account', 'bsb' => 'BSB account',
            ][$a->platform] ?? '',
        };
    }

    /** Accounts of this company still waiting for or in the middle of a fetch. */
    protected function runningSync()
    {
        return $this->accountsQuery()
            ->whereNotNull('sync_queued_at')
            ->where('sync_queued_at', '>', now()->subMinutes(self::SYNC_STALE_MINUTES));
    }

    public function disconnect($id)
    {
        $this->authorize('social-disconnect');

        // Unlinking a Page also removes its Instagram account.
        $this->accountsQuery()->findOrFail($id)->delete();

        if ((int) $this->account === (int) $id) {
            $this->account = '';
        }

        $this->dispatch('swal:success', ['title' => 'Disconnected', 'text' => 'The account and its fetched data were removed.']);
    }

    // ----------------------------------------------------------------- Data

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

    protected function companiesList(bool $search): Collection
    {
        $term = trim($this->companySearch);

        return Company::visibleTo(auth()->user())
            ->when($search && $term !== '', fn ($q) => $q->where('name', 'like', '%' . $term . '%'))
            ->withCount('socialAccounts')
            ->withMax('socialAccounts', 'last_synced_at')
            ->orderBy('name')
            ->get();
    }

    public function render()
    {
        $viewer = auth()->user();
        $company = $this->currentCompany();

        if (!$company) {
            return view('livewire.social.social-media-view', [
                'mode' => $viewer->canAccessAllCompanies() ? 'picker' : 'no-company',
                'pickerCompanies' => $viewer->canAccessAllCompanies() ? $this->companiesList(true) : collect(),
                'companyTotal' => $viewer->canAccessAllCompanies() ? Company::count() : 0,
            ]);
        }

        $accounts = $this->accountsQuery()
            ->orderedByPlatform()
            ->orderBy('name')
            ->get();
        $platforms = $accounts->pluck('platform')->unique();
        $hasSocial = $platforms->intersect(SocialAccount::SOCIAL)->isNotEmpty();
        $hasMetaAds = $platforms->contains(SocialAccount::ADS);
        $hasGoogleAds = $platforms->contains(SocialAccount::GOOGLE_ADS);
        $hasAds = $hasMetaAds || $hasGoogleAds;
        $hasBsb = $platforms->contains(SocialAccount::BSB);

        // Keep the tab on something this company has.
        $available = array_keys(array_filter([
            'overview' => $hasSocial, 'posts' => $hasSocial, 'audience' => $hasSocial, 'messages' => $hasSocial,
            'ads' => $hasAds, 'sms' => $hasBsb,
        ]));
        if ($available && !in_array($this->tab, $available, true)) {
            $this->tab = $available[0];
        }
        // ...and the Ads tab on an ad platform it has.
        if ($this->adPlatform === 'google' ? !$hasGoogleAds : ($this->adPlatform === 'meta' ? !$hasMetaAds : true)) {
            $this->adPlatform = $hasMetaAds ? 'meta' : ($hasGoogleAds ? 'google' : '');
            $this->syncBreakdownChoice();
        }

        $scoped = $this->account ? $accounts->where('id', (int) $this->account)->values() : $accounts;
        $socialIds = $scoped->whereIn('platform', SocialAccount::SOCIAL)->pluck('id');
        $adIds = $scoped->where('platform', SocialAccount::ADS)->pluck('id');
        $googleIds = $scoped->where('platform', SocialAccount::GOOGLE_ADS)->pluck('id');
        $bsbIds = $scoped->where('platform', SocialAccount::BSB)->pluck('id');
        $hasMetaAccounts = $platforms->intersect(SocialAccount::META)->isNotEmpty();

        $data = [
            'mode' => 'company',
            'currentCompany' => $company,
            'switchCompanies' => $viewer->canAccessAllCompanies() ? $this->companiesList(false) : collect(),
            'accounts' => $accounts,
            'selectedAccount' => $this->account ? $accounts->firstWhere('id', (int) $this->account) : null,
            'hasSocial' => $hasSocial,
            'hasAds' => $hasAds,
            'hasMetaAds' => $hasMetaAds,
            'hasGoogleAds' => $hasGoogleAds,
            'hasBsb' => $hasBsb,
            'metaConfigured' => $company->hasMetaApp(),
            'googleConfigured' => $company->hasGoogleAdsApp(),
            // A missing app only matters once accounts depend on it.
            'metaMissing' => $hasMetaAccounts && !$company->hasMetaApp(),
            'googleMissing' => $hasGoogleAds && !$company->hasGoogleAdsApp(),
            'lastFetched' => $accounts->max('last_synced_at'),
            'neverFetched' => $accounts->isNotEmpty() && $accounts->every(fn ($a) => !$a->last_synced_at),
            'warningAccounts' => $accounts->filter(fn ($a) => $a->last_sync_error)->values(),
            'expiringAds' => $accounts->filter(fn ($a) => $a->platform === SocialAccount::ADS
                && $a->token_expires_at && $a->token_expires_at->lt(now()->addDays(7)))->values(),
            'postDetail' => null,
            'syncLeft' => $this->syncing ? $this->runningSync()->count() : 0,
            'syncRun' => $this->syncing ? $this->runAccounts($runId = $this->currentRunId()) : collect(),
            'syncStates' => $this->syncing ? $this->runStates($runId ?? null) : [],
        ];

        if ($accounts->isNotEmpty()) {
            $data += match ($this->tab) {
                'overview' => $this->overviewData($scoped->whereIn('id', $socialIds)->values()),
                'posts' => $this->postsData($scoped->whereIn('id', $socialIds)->values(), $accounts),
                'audience' => $this->audienceData($scoped->whereIn('id', $socialIds)->values()),
                'messages' => $this->messagesData($socialIds),
                'ads' => $this->adPlatform === 'google' ? $this->googleAdsData($googleIds) : $this->adsData($adIds),
                'sms' => $this->bulkData($bsbIds),
            };

            if ($this->openPost) {
                $data = array_merge($data, $this->postDetailData($socialIds));
            }
        }

        return view('livewire.social.social-media-view', $data);
    }

    /**
     * KPIs with change vs the previous period, a daily trend chart, top posts
     * and a per-account comparison.
     */
    protected function overviewData(Collection $socialAccounts): array
    {
        $ids = $socialAccounts->pluck('id');
        $catalog = config('meta.daily_metrics');
        $today = now()->startOfDay();
        $since = $today->copy()->subDays($this->days - 1);
        $prevSince = $since->copy()->subDays($this->days);
        // Compare with the previous period only if every account's data reaches back that far.
        $comparable = $socialAccounts->isNotEmpty() && $socialAccounts->every(function ($a) use ($prevSince) {
            $from = $a->meta['fetched_from']
                ?? $a->last_synced_at?->copy()->subDays(config('meta.history_days'))->toDateString();
            return $from && Carbon::parse($from)->lte($prevSince);
        });

        $insights = SocialInsight::whereIn('social_account_id', $ids)
            ->where('date', '>=', $prevSince->toDateString())
            ->get(['social_account_id', 'metric', 'date', 'value']);
        [$current, $previous] = $insights->partition(fn ($i) => $i->date->gte($since));

        $posts = SocialPost::with('account')
            ->whereIn('social_account_id', $ids)
            ->where('posted_at', '>=', $prevSince)
            ->get();
        [$curPosts, $prevPosts] = $posts->partition(fn ($p) => $p->posted_at->gte($since));

        $metricsWith = fn (string $kpi) => collect($catalog)->filter(fn ($m) => ($m['kpi'] ?? null) === $kpi)->keys();

        // Reach: each account's first reach-type metric that has data.
        $reachMetrics = $metricsWith('reach');
        $reachFor = function (Collection $rows, int $accountId) use ($reachMetrics): ?int {
            foreach ($reachMetrics as $metric) {
                $r = $rows->where('social_account_id', $accountId)->where('metric', $metric);
                if ($r->isNotEmpty()) {
                    return (int) $r->sum('value');
                }
            }
            return null;
        };
        $sumReach = function (Collection $rows) use ($ids, $reachFor): ?int {
            $values = $ids->map(fn ($id) => $reachFor($rows, $id))->filter(fn ($v) => $v !== null);
            return $values->isEmpty() ? null : (int) $values->sum();
        };

        // Follower growth: daily new followers (Instagram) or the change in the running total (Pages).
        $gain = null;
        foreach ($ids as $id) {
            $value = null;
            foreach ($metricsWith('new_followers') as $metric) {
                $rows = $current->where('social_account_id', $id)->where('metric', $metric);
                if ($rows->isNotEmpty()) {
                    $value = (int) $rows->sum('value');
                    break;
                }
            }
            if ($value === null) {
                foreach ($metricsWith('followers_total') as $metric) {
                    $rows = $current->where('social_account_id', $id)->where('metric', $metric)->sortBy('date');
                    $before = $previous->where('social_account_id', $id)->where('metric', $metric)->sortBy('date')->last();
                    $base = $before ?? $rows->first();
                    if ($rows->isNotEmpty() && $base && ($before || $rows->count() > 1)) {
                        $value = (int) $rows->last()->value - (int) $base->value;
                        break;
                    }
                }
            }
            if ($value !== null) {
                $gain = ($gain ?? 0) + $value;
            }
        }

        $engagement = fn (Collection $list) => (int) $list->sum(fn ($p) => $p->engagement);
        $reach = $sumReach($current);

        $kpis = [
            'followers' => [
                'value' => $socialAccounts->sum(fn ($a) => (int) ($a->meta['followers'] ?? 0)),
                'gain' => $gain,
            ],
            'reach' => [
                'value' => $reach,
                'change' => $comparable ? Num::change($reach, $sumReach($previous)) : null,
            ],
            'engagement' => [
                'value' => $engagement($curPosts),
                'change' => $comparable ? Num::change($engagement($curPosts), $engagement($prevPosts)) : null,
                'perPost' => $curPosts->count() ? (int) round($engagement($curPosts) / $curPosts->count()) : null,
            ],
            'posts' => [
                'value' => $curPosts->count(),
                'change' => $comparable ? Num::change($curPosts->count(), $prevPosts->count()) : null,
            ],
        ];

        // Trend chart: metrics grouped by label, so Facebook and Instagram
        // "Reach" share one chart with a line per account.
        $groups = [];
        $present = $current->pluck('metric')->unique();
        $ordered = collect(array_keys($catalog))->filter(fn ($m) => $present->contains($m))
            ->merge($present->diff(array_keys($catalog)));
        foreach ($ordered as $metric) {
            $label = $catalog[$metric]['label'] ?? Str::headline($metric);
            $key = Str::slug($label);
            $groups[$key]['label'] = $label;
            $groups[$key]['agg'] ??= $catalog[$metric]['agg'] ?? 'sum';
            $groups[$key]['metrics'][] = $metric;
        }
        $trendKey = isset($groups[$this->trendMetric]) ? $this->trendMetric : array_key_first($groups);

        $dates = collect(CarbonPeriod::create($since, $today))->map(fn ($d) => $d->toDateString());
        $series = [];
        if ($trendKey) {
            foreach ($socialAccounts as $acc) {
                foreach ($groups[$trendKey]['metrics'] as $metric) {
                    $rows = $current->where('social_account_id', $acc->id)->where('metric', $metric)
                        ->keyBy(fn ($r) => $r->date->toDateString());
                    if ($rows->isEmpty()) {
                        continue;
                    }
                    $values = $dates->map(fn ($d) => isset($rows[$d]) ? (int) $rows[$d]->value : null);
                    $series[] = [
                        'name' => $acc->display_name,
                        'platform' => $acc->platform,
                        'data' => $values->all(),
                        'summary' => $groups[$trendKey]['agg'] === 'last'
                            ? $values->filter(fn ($v) => $v !== null)->last()
                            : $values->sum(),
                    ];
                    break;
                }
            }
        }

        return [
            'kpis' => $kpis,
            'trendGroups' => collect($groups)->map(fn ($g) => $g['label']),
            'trendKey' => $trendKey,
            'trend' => [
                'label' => $trendKey ? $groups[$trendKey]['label'] : null,
                'agg' => $trendKey ? $groups[$trendKey]['agg'] : 'sum',
                'dates' => $dates->all(),
                'series' => $series,
            ],
            'topPosts' => $curPosts->sortByDesc(fn ($p) => $p->engagement)->take(5)->values(),
            'compare' => $socialAccounts->map(function ($acc) use ($curPosts, $current, $reachFor, $engagement) {
                $own = $curPosts->where('social_account_id', $acc->id);
                return [
                    'account' => $acc,
                    'followers' => $acc->meta['followers'] ?? null,
                    'posts' => $own->count(),
                    'engagement' => $engagement($own),
                    'perPost' => $own->count() ? (int) round($engagement($own) / $own->count()) : null,
                    'reach' => $reachFor($current, $acc->id),
                ];
            }),
            'comparable' => $comparable,
        ];
    }

    /**
     * Posts of the accounts in scope, optionally one platform only, with the
     * counts per platform and the accounts offered in the filter.
     */
    protected function postsData(Collection $scopedSocial, Collection $allAccounts): array
    {
        $term = trim($this->postSearch);
        $search = fn ($q) => $q->when($term !== '', fn ($q) => $q->where('message', 'like', '%' . $term . '%'));
        $ids = $scopedSocial
            ->when($this->postPlatform, fn ($c) => $c->where('platform', $this->postPlatform))
            ->pluck('id');

        $query = SocialPost::with('account')->whereIn('social_account_id', $ids)->tap($search);

        match ($this->postSort) {
            'engagement' => $query->orderByRaw('(like_count + comment_count + share_count) DESC'),
            'comments' => $query->orderByDesc('comment_count'),
            'oldest' => $query->oldest('posted_at'),
            default => $query->latest('posted_at'),
        };

        // Matching posts per platform across all the company's accounts (search
        // applied, account and platform filters not), so the switch always offers both.
        $social = $allAccounts->whereIn('platform', [SocialAccount::FACEBOOK, SocialAccount::INSTAGRAM])->values();
        $perAccount = SocialPost::whereIn('social_account_id', $social->pluck('id'))
            ->tap($search)
            ->selectRaw('social_account_id, COUNT(*) as n')
            ->groupBy('social_account_id')
            ->pluck('n', 'social_account_id');
        $platformCounts = $social->groupBy('platform')
            ->map(fn ($list) => (int) $list->sum(fn ($a) => $perAccount[$a->id] ?? 0));

        return [
            'posts' => $query->paginate(12),
            'postTotal' => SocialPost::whereIn('social_account_id', $social->pluck('id'))->count(),
            'platformCounts' => $platformCounts,
            'postAccounts' => $social,
        ];
    }

    /** The open post with its comments as a two-level thread. */
    protected function postDetailData(Collection $socialIds): array
    {
        $post = SocialPost::with('account')->whereIn('social_account_id', $socialIds)->find($this->openPost);
        if (!$post) {
            return ['postDetail' => null];
        }

        $comments = $post->comments()->orderBy('commented_at')->get();
        $byId = $comments->keyBy('external_id');
        // Attach every reply to its top-level comment, however deep it is nested.
        $rootOf = function ($c) use ($byId) {
            $seen = 0;
            while ($c->parent_external_id && isset($byId[$c->parent_external_id]) && $seen++ < 20) {
                $c = $byId[$c->parent_external_id];
            }
            return $c->external_id;
        };
        $grouped = $comments->groupBy($rootOf);

        return [
            'postDetail' => $post,
            'postThreads' => $comments->filter(fn ($c) => $rootOf($c) === $c->external_id)
                ->map(fn ($c) => ['comment' => $c, 'replies' => $grouped[$c->external_id]->reject(fn ($r) => $r->is($c))->values()])
                ->values(),
        ];
    }

    protected function audienceData(Collection $socialAccounts): array
    {
        return [
            'audienceAccounts' => $socialAccounts,
            'demographics' => SocialDemographic::whereIn('social_account_id', $socialAccounts->pluck('id'))
                ->whereIn('metric', array_merge([$this->audienceMetric], array_keys(config('meta.demographics.facebook_page'))))
                ->orderByDesc('value')
                ->get()
                ->groupBy(['social_account_id', 'dimension']),
        ];
    }

    /**
     * Conversations with their response times over the period: totals, a
     * distribution, one row per account, and the conversations still waiting.
     */
    protected function messagesData(Collection $socialIds): array
    {
        $term = trim($this->chatSearch);
        $since = now()->startOfDay()->subDays($this->days - 1);

        $messages = SocialMessage::query()
            ->join('social_conversations', 'social_conversations.id', '=', 'social_messages.social_conversation_id')
            ->whereIn('social_conversations.social_account_id', $socialIds)
            ->where('social_messages.sent_at', '>=', $since)
            ->get(['social_messages.social_conversation_id', 'social_messages.from_page', 'social_messages.sent_at', 'social_conversations.social_account_id']);
        $stats = ResponseTimes::perConversation($messages, $since);
        $accountOf = $messages->pluck('social_account_id', 'social_conversation_id');
        $awaitingIds = array_keys(array_filter($stats, fn ($c) => $c['awaitingSince'] !== null));

        $socialAccounts = SocialAccount::whereIn('id', $socialIds)->orderedByPlatform()->orderBy('name')->get();

        $chat = $this->openConversation
            ? SocialConversation::with(['account', 'messages' => fn ($q) => $q->orderBy('sent_at')])
                ->whereIn('social_account_id', $socialIds)
                ->find($this->openConversation)
            : null;

        return [
            'conversations' => SocialConversation::with('account')
                ->whereIn('social_account_id', $socialIds)
                ->when($this->chatFilter === 'awaiting', fn ($q) => $q->whereKey($awaitingIds))
                ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w
                    ->where('participant_name', 'like', '%' . $term . '%')
                    ->orWhere('snippet', 'like', '%' . $term . '%')))
                ->latest('last_message_at')
                ->paginate(20),
            'chat' => $chat,
            'chatDays' => $chat ? $chat->messages->groupBy(fn ($m) => $m->sent_at?->toDateString() ?? '') : collect(),
            'responseStats' => $stats,
            'responseSummary' => ResponseTimes::summary($stats),
            'responseByAccount' => $socialAccounts->map(fn ($acc) => [
                'account' => $acc,
                'summary' => ResponseTimes::summary(array_filter($stats, fn ($id) => (int) ($accountOf[$id] ?? 0) === $acc->id, ARRAY_FILTER_USE_KEY)),
            ])->filter(fn ($row) => $row['summary']['conversations'] > 0 || $row['summary']['responses'] > 0)->values(),
        ];
    }

    /**
     * Google Ads: totals and campaigns over the period of the last fetch, the
     * account's results per day, and campaign results by audience segment.
     */
    protected function googleAdsData(Collection $ids): array
    {
        $adAccounts = SocialAccount::whereIn('id', $ids)->orderBy('name')->get();
        $currencies = $adAccounts->pluck('meta.currency')->filter()->unique();
        $since = $adAccounts->pluck('meta.last_fetch_since')->filter()->min();

        $campaigns = SocialAd::with('account')->whereIn('social_account_id', $ids);
        match ($this->adSort) {
            'newest' => $campaigns->latest('started_at'),
            'clicks' => $campaigns->orderByDesc('clicks'),
            'conversions' => $campaigns->orderByDesc('conversions'),
            default => $campaigns->orderByDesc('spend'),
        };

        $breakdownAd = $this->breakdownAd ? SocialAd::whereIn('social_account_id', $ids)->find($this->breakdownAd) : null;
        $rows = SocialAdBreakdown::whereIn('social_account_id', $ids)->where('breakdown', $this->breakdown);
        if ($breakdownAd) {
            $rows->where('social_account_id', $breakdownAd->social_account_id)->where('ad_external_id', $breakdownAd->external_id);
        }
        $rows->selectRaw('segment, SUM(spend) as spend, SUM(impressions) as impressions, SUM(clicks) as clicks, SUM(conversions) as conversions')
            ->groupBy('segment');
        $this->breakdown === 'age'
            ? $rows->orderBy('segment')
            : $rows->orderByDesc('spend')->orderByDesc('impressions');

        // Daily chart over the fetch period: one line per account, days without results count as 0.
        $from = $since ? Carbon::parse($since) : now()->subDays(29);
        $dates = collect(CarbonPeriod::create($from->startOfDay(), now()->startOfDay()))->map(fn ($d) => $d->toDateString());
        $daily = SocialAdDailyStat::whereIn('social_account_id', $ids)->where('date', '>=', $from->toDateString())->get();
        $metric = $this->googleTrend;
        $series = $adAccounts->map(function ($acc) use ($daily, $dates, $metric) {
            $byDate = $daily->where('social_account_id', $acc->id)->keyBy(fn ($r) => $r->date->toDateString());
            $values = $dates->map(fn ($d) => isset($byDate[$d]) ? round((float) $byDate[$d]->$metric, 2) : 0);
            return ['name' => $acc->display_name, 'platform' => $acc->platform, 'data' => $values->all(), 'summary' => round($values->sum(), 2)];
        })->filter(fn ($s) => $daily->isNotEmpty())->values();

        return [
            'gCampaigns' => $campaigns->paginate(15),
            'gTotals' => SocialAd::whereIn('social_account_id', $ids)
                ->selectRaw('SUM(spend) as spend, SUM(impressions) as impressions, SUM(clicks) as clicks, SUM(conversions) as conversions,'
                    . " SUM(conversion_value) as conversion_value, SUM(interactions) as interactions, SUM(engagements) as engagements,"
                    . " SUM(video_views) as video_views, COUNT(*) as total, SUM(status = 'ENABLED') as active")
                ->first(),
            'currency' => $currencies->count() === 1 ? $currencies->first() : null,
            'mixedCurrency' => $currencies->count() > 1,
            'adsSince' => $since,
            'breakdownAdModel' => $breakdownAd,
            'breakdownRows' => $rows->limit(50)->get(),
            'gTrend' => [
                'label' => self::GOOGLE_TREND_METRICS[$metric],
                'agg' => 'sum',
                'dates' => $dates->all(),
                'series' => $series->all(),
            ],
        ];
    }

    /**
     * BSB messages first seen in the period: the delivery funnel, per channel,
     * per day and per country, and the message list with its filters.
     */
    protected function bulkData(Collection $ids): array
    {
        $since = now()->startOfDay()->subDays($this->days - 1);
        $inPeriod = fn () => SocialBulkMessage::whereIn('social_account_id', $ids)->where('first_seen_at', '>=', $since);
        // Funnel groups: delivered, still on the way (sent / pending / unknown), failed.
        $group = "CASE WHEN status = 'DELIVERED' THEN 'delivered' WHEN status = 'FAILED' THEN 'failed' ELSE 'progress' END";

        $byRoute = $inPeriod()->selectRaw("route, $group as grp, COUNT(*) as n")->groupBy('route', 'grp')->get();
        $funnel = fn (Collection $rows) => [
            'total' => (int) $rows->sum('n'),
            'delivered' => (int) $rows->where('grp', 'delivered')->sum('n'),
            'progress' => (int) $rows->where('grp', 'progress')->sum('n'),
            'failed' => (int) $rows->where('grp', 'failed')->sum('n'),
        ];

        $dates = collect(CarbonPeriod::create($since, now()->startOfDay()))->map(fn ($d) => $d->toDateString());
        $daily = $inPeriod()->selectRaw("DATE(first_seen_at) as d, $group as grp, COUNT(*) as n")->groupBy('d', 'grp')->get()
            ->groupBy('grp')->map(fn ($rows) => $rows->pluck('n', 'd'));
        $line = fn (string $grp, string $name) => [
            'name' => $name, 'platform' => $grp,
            'data' => $dates->map(fn ($d) => (int) ($daily[$grp][$d] ?? 0))->all(),
            'summary' => (int) collect($daily[$grp] ?? [])->sum(),
        ];

        $term = preg_replace('/\D/', '', $this->smsSearch);
        $list = $inPeriod()->with('account')
            ->when($this->smsRoute, fn ($q) => $q->where('route', $this->smsRoute))
            ->when($this->smsStatus === 'delivered', fn ($q) => $q->where('status', 'DELIVERED'))
            ->when($this->smsStatus === 'failed', fn ($q) => $q->where('status', 'FAILED'))
            ->when($this->smsStatus === 'progress', fn ($q) => $q->where(fn ($w) => $w->whereNull('status')->orWhereNotIn('status', SocialBulkMessage::FINAL)))
            ->when($term !== '', fn ($q) => $q->where('recipient', 'like', '%' . $term . '%'))
            ->latest('first_seen_at')
            ->orderByDesc('id');

        $bsbAccounts = SocialAccount::whereIn('id', $ids)->orderBy('name')->get();
        $staleAfter = now()->subMinutes(max(30, 3 * (int) config('bsb.poll_minutes')));

        return [
            'bsbAccounts' => $bsbAccounts,
            'smsFunnel' => $funnel($byRoute),
            'smsByRoute' => collect(SocialBulkMessage::ROUTES)->map(fn ($label, $route) => ['label' => $label] + $funnel($byRoute->where('route', $route)))
                ->filter(fn ($r) => $r['total'] > 0),
            'smsTrend' => [
                'label' => 'Messages',
                'agg' => 'sum',
                'dates' => $dates->all(),
                'series' => [$line('delivered', 'Delivered'), $line('progress', 'On the way'), $line('failed', 'Failed')],
                'colors' => ['#16A34A', '#94A3B8', '#DC2626'],
            ],
            'smsCountries' => $inPeriod()->selectRaw("country, COUNT(*) as n, SUM(status = 'DELIVERED') as delivered, SUM(status = 'FAILED') as failed")
                ->groupBy('country')->orderByDesc('n')->limit(10)->get(),
            'smsMessages' => $list->paginate(25),
            'smsLogStart' => SocialBulkMessage::whereIn('social_account_id', $ids)->min('first_seen_at'),
            // Automatic checks look stopped when no account was checked recently.
            'smsChecksStale' => $bsbAccounts->isNotEmpty() && $bsbAccounts->every(fn ($a) => !$a->last_synced_at || $a->last_synced_at->lt($staleAfter)),
        ];
    }

    protected function adsData(Collection $adIds): array
    {
        $adAccounts = SocialAccount::whereIn('id', $adIds)->get();
        $currencies = $adAccounts->pluck('meta.currency')->filter()->unique();

        $ads = SocialAd::with('account')->whereIn('social_account_id', $adIds);
        match ($this->adSort) {
            'newest' => $ads->latest('started_at'),
            'clicks' => $ads->orderByDesc('clicks'),
            default => $ads->orderByDesc('spend'),
        };

        $breakdownAd = $this->breakdownAd ? SocialAd::whereIn('social_account_id', $adIds)->find($this->breakdownAd) : null;
        $rows = SocialAdBreakdown::whereIn('social_account_id', $adIds)->where('breakdown', $this->breakdown);
        if ($breakdownAd) {
            $rows->where('social_account_id', $breakdownAd->social_account_id)->where('ad_external_id', $breakdownAd->external_id);
        }
        $rows->selectRaw('segment, SUM(spend) as spend, SUM(impressions) as impressions, SUM(reach) as reach, SUM(clicks) as clicks')
            ->groupBy('segment');
        // Age & gender reads best in age order; the others by spend.
        $this->breakdown === 'age_gender'
            ? $rows->orderBy('segment')
            : $rows->orderByDesc('spend')->orderByDesc('impressions');

        return [
            'ads' => $ads->paginate(15),
            'adTotals' => SocialAd::whereIn('social_account_id', $adIds)
                ->selectRaw("SUM(spend) as spend, SUM(impressions) as impressions, SUM(reach) as reach, SUM(clicks) as clicks, COUNT(*) as total, SUM(effective_status = 'ACTIVE') as active")
                ->first(),
            'currency' => $currencies->count() === 1 ? $currencies->first() : null,
            // Ad figures cover the period of the last fetch.
            'adsSince' => $adAccounts->pluck('meta.last_fetch_since')->filter()->min(),
            'mixedCurrency' => $currencies->count() > 1,
            'breakdownAdModel' => $breakdownAd,
            'breakdownRows' => $rows->limit(50)->get(),
        ];
    }
}
