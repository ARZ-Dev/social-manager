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
use App\Support\Num;
use Carbon\CarbonPeriod;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
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

    public string $companySearch = '';
    public string $chatSearch = '';
    public string $audienceMetric = 'follower_demographics';
    public string $breakdown = 'age_gender';
    public string $adSort = 'spend';

    public ?int $openPost = null;
    public ?int $openConversation = null;
    public ?int $breakdownAd = null;

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
    }

    // ------------------------------------------------------------ Navigation

    public function selectCompany($id)
    {
        abort_unless(auth()->user()->canAccessAllCompanies(), 403);

        $this->company = Company::visibleTo(auth()->user())->findOrFail($id)->id;
        $this->reset('account', 'trendMetric', 'postSearch', 'chatSearch', 'companySearch', 'openPost', 'openConversation', 'breakdownAd');
        $this->resetPage();
    }

    /** Back to the company picker. */
    public function clearCompany()
    {
        abort_unless(auth()->user()->canAccessAllCompanies(), 403);

        $this->reset('company', 'account', 'trendMetric', 'postSearch', 'chatSearch', 'openPost', 'openConversation', 'breakdownAd');
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

    public function updatedChatSearch()
    {
        $this->resetPage();
    }

    public function updatedAdSort()
    {
        $this->resetPage();
    }

    // --------------------------------------------------------------- Actions

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
            $this->dispatch('swal:error', ['title' => 'Nothing to fetch', 'text' => 'Connect a Facebook Page first.']);
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

        $summary = collect($totals)->filter()->map(fn ($v, $k) => number_format($v) . " $k")->join(', ', ' and ') ?: 'no new data';
        $this->dispatch($warnings ? 'swal:warning' : 'swal:success', [
            'title' => $warnings ? 'Fetched, with warnings' : 'Data updated',
            'text' => "Fetched $summary." . ($warnings ? " Meta refused $warnings request(s). The warnings panel on the page lists them." : ''),
        ]);
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
        ];

        if ($accounts->isNotEmpty()) {
            $data += match ($this->tab) {
                'overview' => $this->overviewData($scoped->whereIn('id', $socialIds)->values()),
                'posts' => $this->postsData($socialIds),
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
        // The previous period only counts if it is inside the fetched history.
        $comparable = $this->days * 2 <= config('meta.history_days');

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

    protected function postsData(Collection $socialIds): array
    {
        $term = trim($this->postSearch);
        $query = SocialPost::with('account')
            ->whereIn('social_account_id', $socialIds)
            ->when($term !== '', fn ($q) => $q->where('message', 'like', '%' . $term . '%'));

        match ($this->postSort) {
            'engagement' => $query->orderByRaw('(like_count + comment_count + share_count) DESC'),
            'comments' => $query->orderByDesc('comment_count'),
            'oldest' => $query->oldest('posted_at'),
            default => $query->latest('posted_at'),
        };

        return [
            'posts' => $query->paginate(12),
            'postTotal' => SocialPost::whereIn('social_account_id', $socialIds)->count(),
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
        $currencies = SocialAccount::whereIn('id', $adIds)->get()->pluck('meta.currency')->filter()->unique();

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
            'mixedCurrency' => $currencies->count() > 1,
            'breakdownAdModel' => $breakdownAd,
            'breakdownRows' => $rows->limit(50)->get(),
        ];
    }
}
