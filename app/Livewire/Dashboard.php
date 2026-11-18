<?php

namespace App\Livewire;

use App\Models\Company;
use App\Models\Social\SocialAccount;
use App\Models\Social\SocialPost;
use App\Models\User;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Home page: a live summary of what the viewer can see (their company, or
 * every company for Super Admins) and what needs attention.
 */
class Dashboard extends Component
{
    public function render()
    {
        $viewer = auth()->user();
        $canSocial = $viewer->can('social-list');
        $allCompanies = $viewer->canAccessAllCompanies();

        $companies = Company::visibleTo($viewer)
            ->withCount('socialAccounts')
            ->withMax('socialAccounts', 'last_synced_at')
            ->orderBy('name')
            ->get();

        $accounts = $canSocial ? SocialAccount::visibleTo($viewer)->with('company')->get() : collect();
        $social = $accounts->whereIn('platform', [SocialAccount::FACEBOOK, SocialAccount::INSTAGRAM]);

        $weekPosts = $canSocial && $social->isNotEmpty()
            ? SocialPost::with('account')
                ->whereIn('social_account_id', $social->pluck('id'))
                ->where('posted_at', '>=', now()->subDays(7))
                ->get()
            : collect();

        return view('livewire.dashboard-view', [
            'canSocial' => $canSocial,
            'allCompanies' => $allCompanies,
            'stats' => $this->stats($viewer, $companies, $accounts, $social, $weekPosts),
            'companyRows' => $this->companyRows($companies, $social, $accounts),
            'accounts' => $accounts,
            'attention' => $canSocial ? $this->attention($companies, $accounts, $allCompanies) : collect(),
            'topPosts' => $weekPosts->sortByDesc(fn ($p) => $p->engagement)->take(5)->values(),
            'lastFetched' => $accounts->max('last_synced_at'),
        ]);
    }

    protected function stats(User $viewer, Collection $companies, Collection $accounts, Collection $social, Collection $weekPosts): array
    {
        $stats = [];

        if ($viewer->can('social-list')) {
            $stats[] = ['label' => 'Followers', 'value' => $social->sum(fn ($a) => (int) ($a->meta['followers'] ?? 0)), 'icon' => 'users-group',
                'note' => $social->count() . ' Facebook & Instagram ' . str('account')->plural($social->count())];
            $stats[] = ['label' => 'Engagement, 7 days', 'value' => (int) $weekPosts->sum(fn ($p) => $p->engagement), 'icon' => 'heart',
                'note' => 'Likes, comments and shares'];
            $stats[] = ['label' => 'Posts, 7 days', 'value' => $weekPosts->count(), 'icon' => 'photo',
                'note' => 'Published across all accounts'];
        }

        if ($viewer->canAccessAllCompanies() && $viewer->can('company-list')) {
            $stats[] = ['label' => 'Companies', 'value' => $companies->count(), 'icon' => 'building',
                'note' => $companies->where('social_accounts_count', '>', 0)->count() . ' with linked accounts'];
        } elseif ($viewer->can('user-list')) {
            $stats[] = ['label' => 'Users', 'value' => User::visibleTo($viewer)->count(), 'icon' => 'user', 'note' => 'With access to this workspace'];
        }

        return array_slice($stats, 0, 4);
    }

    protected function companyRows(Collection $companies, Collection $social, Collection $accounts): Collection
    {
        return $companies->map(fn ($c) => [
            'company' => $c,
            'accounts' => $c->social_accounts_count,
            'followers' => $social->where('company_id', $c->id)->sum(fn ($a) => (int) ($a->meta['followers'] ?? 0)),
            'lastFetched' => $c->social_accounts_max_last_synced_at ? \Illuminate\Support\Carbon::parse($c->social_accounts_max_last_synced_at) : null,
            'warnings' => $accounts->where('company_id', $c->id)->filter(fn ($a) => $a->last_sync_error)->count(),
            'metaApp' => $c->hasMetaApp(),
        ]);
    }

    /** Things someone should act on, most urgent first. */
    protected function attention(Collection $companies, Collection $accounts, bool $allCompanies): Collection
    {
        $items = collect();

        foreach ($accounts->where('platform', SocialAccount::ADS) as $acc) {
            if ($acc->token_expires_at && $acc->token_expires_at->lt(now()->addDays(7))) {
                $expired = $acc->token_expires_at->isPast();
                $items->push([
                    'level' => $expired ? 'red' : 'yellow',
                    'icon' => 'clock-exclamation',
                    'title' => $expired ? 'Ad account access expired' : 'Ad account access expires in ' . $acc->token_expires_at->diffForHumans(null, true),
                    'text' => $acc->display_name . ' · ' . $acc->company?->name,
                    'url' => route('social', ['company' => $acc->company_id]),
                    'cta' => 'Reconnect',
                ]);
            }
        }

        foreach ($accounts->filter(fn ($a) => $a->last_sync_error)->groupBy('company_id') as $companyId => $list) {
            $items->push([
                'level' => 'yellow',
                'icon' => 'alert-triangle',
                'title' => $list->count() . ' ' . str('account')->plural($list->count()) . ' had fetch warnings',
                'text' => $list->first()->company?->name,
                'url' => route('social', ['company' => $companyId]),
                'cta' => 'Review',
            ]);
        }

        foreach ($accounts->groupBy('company_id') as $companyId => $list) {
            if ($list->every(fn ($a) => !$a->last_synced_at)) {
                $items->push([
                    'level' => 'blue',
                    'icon' => 'refresh',
                    'title' => 'Linked accounts never fetched',
                    'text' => $list->first()->company?->name,
                    'url' => route('social', ['company' => $companyId]),
                    'cta' => 'Fetch',
                ]);
            }
        }

        if ($allCompanies) {
            foreach ($companies->filter(fn ($c) => $c->is_active && !$c->hasMetaApp()) as $c) {
                $items->push([
                    'level' => 'gray',
                    'icon' => 'plug-connected-x',
                    'title' => 'No Meta app set up',
                    'text' => $c->name,
                    'url' => auth()->user()->can('company-edit') ? route('companies.edit', $c->id) . '#meta-app' : null,
                    'cta' => 'Set up',
                ]);
            }
        }

        $order = ['red' => 0, 'yellow' => 1, 'blue' => 2, 'gray' => 3];

        return $items->sortBy(fn ($i) => $order[$i['level']])->values();
    }
}
