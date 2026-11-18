<?php

namespace App\Livewire\Social;

use App\Jobs\SyncSocialAccounts;
use App\Models\Company;
use App\Models\Social\SocialAccount;
use App\Models\Social\SocialAd;
use App\Models\Social\SocialAdBreakdown;
use App\Models\Social\SocialConversation;
use App\Models\Social\SocialDemographic;
use App\Models\Social\SocialInsight;
use App\Models\Social\SocialPost;
use App\Support\Num;
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
 * their own. Shows the company's linked Facebook / Instagram / ad accounts
 * and the data fetched from them: overview, posts, audience, messages, ads.
 */
class SocialMediaView extends Component
{
    use AuthorizesRequests, WithPagination;

    public const TABS = ['overview', 'posts', 'audience', 'messages', 'ads'];
    public const PERIODS = [7, 30, 90];

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

    public string $companySearch = '';
    public string $chatSearch = '';
    public string $audienceMetric = 'follower_demographics';
    public string $breakdown = 'age_gender';
    public string $adSort = 'spend';

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
        $this->reset('account', 'trendMetric', 'postSearch', 'chatSearch', 'companySearch', 'openPost', 'openConversation', 'breakdownAd');
        $this->syncing = $this->runningSync()->exists();
        $this->resetPage();
    }

    /** Back to the company picker. */
    public function clearCompany()
    {
        abort_unless(auth()->user()->canAccessAllCompanies(), 403);

        $this->reset('company', 'account', 'trendMetric', 'postSearch', 'chatSearch', 'openPost', 'openConversation', 'breakdownAd', 'syncing');
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
        if ($acc?->platform === SocialAccount::ADS) {
            $this->tab = 'ads';
        } elseif ($acc && $this->tab === 'ads') {
            $this->tab = 'overview';
        }
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
        $this->breakdown = array_key_exists($name, config('meta.ad_breakdowns')) ? $name : 'age_gender';
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
            $this->dispatch('swal:error', ['title' => 'Nothing to fetch', 'text' => 'Connect a Facebook Page first.']);
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
        $ids = $all->filter(fn ($id) => in_array((string) $id, $this->fetchAccounts, true))->values();
        $runId = (string) Str::uuid();

        SocialAccount::whereKey($ids)->update(['sync_queued_at' => now(), 'sync_run_id' => $runId]);
        SyncSocialAccounts::dispatchAfterResponse($ids->all(), $runId, $since->toDateString());

        $this->showFetch = false;
        $this->syncing = true;
    }

    /** Stop the running fetch. What was saved so far is kept. */
    public function stopFetch()
    {
        $this->authorize('social-sync');

        $this->runningSync()->update(['sync_queued_at' => null, 'sync_run_id' => null]);
        $this->syncing = false;
        $this->dispatch('swal:info', [
            'title' => 'Fetch stopped',
            'text' => 'Data fetched so far is kept. The account in progress stops after its current step.',
        ]);
    }

    protected function orderedAccountIds(): Collection
    {
        return $this->accountsQuery()
            ->orderByRaw("FIELD(platform, 'facebook', 'instagram', 'ads')")
            ->orderBy('id')
            ->pluck('id');
    }

    /**
     * Polled every few seconds while a fetch runs. Returns how many accounts are
     * left; the page only re-renders once everything is done.
     */
    public function checkSync(): int
    {
        $left = $this->runningSync()->count();
        if ($left > 0) {
            $this->skipRender();
            return $left;
        }

        if ($this->syncing) {
            $this->syncing = false;
            $warned = $this->accountsQuery()->whereNotNull('last_sync_error')->count();
            $this->dispatch($warned ? 'swal:warning' : 'swal:success', [
                'title' => $warned ? 'Fetched, with warnings' : 'Data updated',
                'text' => $warned
                    ? "Meta refused part of the fetch on $warned " . str('account')->plural($warned) . '. The warnings panel lists what.'
                    : 'All accounts are up to date.',
            ]);
        }

        return 0;
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
            ->orderByRaw("FIELD(platform, 'facebook', 'instagram', 'ads')")
            ->orderBy('name')
            ->get();
        $social = [SocialAccount::FACEBOOK, SocialAccount::INSTAGRAM];
        $hasSocial = $accounts->whereIn('platform', $social)->isNotEmpty();
        $hasAds = $accounts->where('platform', SocialAccount::ADS)->isNotEmpty();

        // Keep the tab on something this company has.
        if (!$hasSocial && $hasAds) {
            $this->tab = 'ads';
        } elseif (!$hasAds && $this->tab === 'ads') {
            $this->tab = 'overview';
        }

        $scoped = $this->account ? $accounts->where('id', (int) $this->account)->values() : $accounts;
        $socialIds = $scoped->whereIn('platform', $social)->pluck('id');
        $adIds = $scoped->where('platform', SocialAccount::ADS)->pluck('id');

        $data = [
            'mode' => 'company',
            'currentCompany' => $company,
            'switchCompanies' => $viewer->canAccessAllCompanies() ? $this->companiesList(false) : collect(),
            'accounts' => $accounts,
            'selectedAccount' => $this->account ? $accounts->firstWhere('id', (int) $this->account) : null,
            'hasSocial' => $hasSocial,
            'hasAds' => $hasAds,
            'metaConfigured' => $company->hasMetaApp(),
            'lastFetched' => $accounts->max('last_synced_at'),
            'neverFetched' => $accounts->isNotEmpty() && $accounts->every(fn ($a) => !$a->last_synced_at),
            'warningAccounts' => $accounts->filter(fn ($a) => $a->last_sync_error)->values(),
            'expiringAds' => $accounts->filter(fn ($a) => $a->platform === SocialAccount::ADS
                && $a->token_expires_at && $a->token_expires_at->lt(now()->addDays(7)))->values(),
            'postDetail' => null,
            'syncLeft' => $this->syncing ? $this->runningSync()->count() : 0,
        ];

        if ($accounts->isNotEmpty()) {
            $data += match ($this->tab) {
                'overview' => $this->overviewData($scoped->whereIn('id', $socialIds)->values()),
                'posts' => $this->postsData($scoped->whereIn('id', $socialIds)->values(), $accounts),
                'audience' => $this->audienceData($scoped->whereIn('id', $socialIds)->values()),
                'messages' => $this->messagesData($socialIds),
                'ads' => $this->adsData($adIds),
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

    protected function messagesData(Collection $socialIds): array
    {
        $term = trim($this->chatSearch);
        $chat = $this->openConversation
            ? SocialConversation::with(['account', 'messages' => fn ($q) => $q->orderBy('sent_at')])
                ->whereIn('social_account_id', $socialIds)
                ->find($this->openConversation)
            : null;

        return [
            'conversations' => SocialConversation::with('account')
                ->whereIn('social_account_id', $socialIds)
                ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w
                    ->where('participant_name', 'like', '%' . $term . '%')
                    ->orWhere('snippet', 'like', '%' . $term . '%')))
                ->latest('last_message_at')
                ->paginate(20),
            'chat' => $chat,
            'chatDays' => $chat ? $chat->messages->groupBy(fn ($m) => $m->sent_at?->toDateString() ?? '') : collect(),
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
