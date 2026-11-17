<div>
    @php
        $platformIcon = [
            'facebook' => 'ti-brand-facebook text-primary',
            'instagram' => 'ti-brand-instagram text-danger',
            'ads' => 'ti-speakerphone text-warning',
        ];
    @endphp

    {{-- Header: company + actions --}}
    <div class="card mb-4">
        <div class="card-body d-flex flex-wrap gap-3 justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <h4 class="mb-0">Social Media</h4>
                @if($companies->isNotEmpty())
                    <select wire:model.live="company" class="form-select w-auto">
                        @foreach($companies as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                @elseif($currentCompany)
                    <span class="badge bg-label-primary">{{ $currentCompany->name }}</span>
                @endif
            </div>
            @if($currentCompany)
                <div class="d-flex gap-2">
                    @can('social-sync')
                        <button wire:click="fetch" wire:loading.attr="disabled" wire:target="fetch" class="btn btn-label-primary" @disabled($accounts->isEmpty())>
                            <span wire:loading.remove wire:target="fetch"><i class="ti ti-refresh me-1"></i> Fetch data</span>
                            <span wire:loading wire:target="fetch"><span class="spinner-border spinner-border-sm me-1"></span> Fetching… this can take a few minutes</span>
                        </button>
                    @endcan
                    @can('social-connect')
                        <a href="{{ $metaConfigured ? route('social.connect', ['company' => $currentCompany->id]) : '#' }}" class="btn btn-primary {{ $metaConfigured ? '' : 'disabled' }}">
                            <i class="ti ti-plug-connected me-1"></i> Connect Facebook &amp; Instagram
                        </a>
                    @endcan
                </div>
            @endif
        </div>
        @if($currentCompany && !$metaConfigured)
            <div class="alert alert-warning mx-4 mb-4">
                <strong>{{ $currentCompany->name }}</strong> has no Meta app yet. Each company connects through its own Meta App ID and App Secret.
                @can('company-edit')
                    <a href="{{ route('companies.edit', $currentCompany->id) }}#meta-app" class="alert-link">Add them in the company settings</a>
                    (the form explains how to get them).
                @else
                    Ask an administrator to add them in the company settings.
                @endcan
            </div>
        @endif
    </div>

    @if(!$currentCompany)
        <div class="card"><div class="card-body text-muted">You are not assigned to a company, so there are no social accounts to show.</div></div>
    @else

    {{-- Linked accounts --}}
    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">Linked accounts</h5></div>
        <div class="card-body">
            @forelse($accounts as $acc)
                <div class="d-flex align-items-center border rounded p-3 mb-2" wire:key="acc-{{ $acc->id }}">
                    @if($acc->picture_url)
                        <img src="{{ $acc->picture_url }}" class="rounded-circle me-3" width="40" height="40" alt="">
                    @else
                        <span class="avatar me-3"><span class="avatar-initial rounded-circle bg-label-secondary"><i class="ti {{ $platformIcon[$acc->platform] }}"></i></span></span>
                    @endif
                    <div class="flex-grow-1">
                        <div class="fw-semibold">
                            <i class="ti {{ $platformIcon[$acc->platform] }}"></i>
                            {{ $acc->platform === 'instagram' && $acc->username ? '@' . $acc->username : ($acc->name ?? $acc->external_id) }}
                            @if(isset($acc->meta['followers']))
                                <small class="text-muted ms-2">{{ number_format($acc->meta['followers']) }} followers</small>
                            @endif
                        </div>
                        <small class="text-muted">
                            {{ $acc->last_synced_at ? 'Last fetched ' . $acc->last_synced_at->diffForHumans() : 'Never fetched' }}
                            @if($acc->token_expires_at)
                                · access {{ $acc->token_expires_at->isPast() ? 'expired' : 'expires ' . $acc->token_expires_at->diffForHumans() }}
                            @endif
                        </small>
                        @if($acc->last_sync_error)
                            <details class="mt-1">
                                <summary class="text-warning small">Last fetch had warnings</summary>
                                <pre class="small text-muted mb-0" style="white-space: pre-wrap">{{ $acc->last_sync_error }}</pre>
                            </details>
                        @endif
                    </div>
                    @can('social-disconnect')
                        <a href="#" class="text-danger delete-button ms-3" data-id="{{ $acc->id }}" title="Disconnect"><i class="ti ti-unlink ti-sm"></i></a>
                    @endcan
                </div>
            @empty
                <p class="text-muted mb-0">No accounts linked yet. Use <strong>Connect Facebook &amp; Instagram</strong> to link this company's Pages.</p>
            @endforelse
        </div>
    </div>

    @if($accounts->isNotEmpty())
    {{-- Tabs --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <ul class="nav nav-pills">
            @foreach(['posts' => ['Posts & comments', 'ti-photo'], 'insights' => ['Insights', 'ti-chart-bar'], 'audience' => ['Audience', 'ti-users'], 'messages' => ['Messages', 'ti-messages'], 'boosts' => ['Boosts', 'ti-speakerphone']] as $key => [$label, $icon])
                <li class="nav-item">
                    <button type="button" wire:click="setTab('{{ $key }}')" class="nav-link {{ $tab === $key ? 'active' : '' }}">
                        <i class="ti {{ $icon }} me-1"></i> {{ $label }}
                    </button>
                </li>
            @endforeach
        </ul>
        <select wire:model.live="account" class="form-select w-auto">
            <option value="">All accounts</option>
            @foreach($accounts as $acc)
                <option value="{{ $acc->id }}">{{ ucfirst($acc->platform) }}: {{ $acc->username ? '@' . $acc->username : $acc->name }}</option>
            @endforeach
        </select>
    </div>

    <div wire:loading.class="opacity-50" wire:target="setTab,account,gotoPage,nextPage,previousPage,setAudienceMetric,setBreakdown,toggleAdBreakdown">

    {{-- Posts --}}
    @if($tab === 'posts')
        <div class="row g-3">
            @forelse($posts as $post)
                <div class="col-12 col-md-6 col-xl-4" wire:key="post-{{ $post->id }}">
                    <div class="card h-100">
                        @if($post->thumbnail_url || $post->media_url)
                            <img src="{{ $post->thumbnail_url ?: $post->media_url }}" class="card-img-top" style="height: 220px; object-fit: cover" alt="" loading="lazy">
                        @endif
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between small text-muted mb-2">
                                <span><i class="ti {{ $platformIcon[$post->account->platform] }}"></i> {{ $post->account->username ? '@' . $post->account->username : $post->account->name }}</span>
                                <span>{{ $post->posted_at?->format('d M Y H:i') }}</span>
                            </div>
                            <p class="mb-3 flex-grow-1">{{ \Illuminate\Support\Str::limit($post->message, 220) ?: '(no caption)' }}</p>
                            <div class="d-flex gap-3 small mb-2">
                                <span title="Likes / reactions"><i class="ti ti-heart"></i> {{ number_format($post->like_count) }}</span>
                                <span title="Comments"><i class="ti ti-message"></i> {{ number_format($post->comment_count) }}</span>
                                @if($post->share_count)
                                    <span title="Shares"><i class="ti ti-share"></i> {{ number_format($post->share_count) }}</span>
                                @endif
                                @if($post->media_type)
                                    <span class="badge bg-label-secondary ms-auto">{{ str_replace('_', ' ', strtolower($post->media_type)) }}</span>
                                @endif
                            </div>
                            @if($post->insights)
                                <div class="small text-muted mb-2">
                                    @foreach($post->insights as $metric => $value)
                                        @if(!is_null($value))
                                            <span class="me-2">{{ str_replace(['post_', '_'], ['', ' '], $metric) }}: <strong>{{ number_format((float) $value) }}</strong></span>
                                        @endif
                                    @endforeach
                                </div>
                            @endif
                            <div class="d-flex justify-content-between">
                                <button type="button" wire:click="togglePost({{ $post->id }})" class="btn btn-sm btn-label-secondary" @disabled(!$post->comment_count)>
                                    {{ $openPost === $post->id ? 'Hide comments' : 'Show comments' }}
                                </button>
                                @if($post->permalink)
                                    <a href="{{ $post->permalink }}" target="_blank" rel="noopener" class="btn btn-sm btn-link">Open <i class="ti ti-external-link"></i></a>
                                @endif
                            </div>
                            @if($openPost === $post->id && $comments)
                                <div class="mt-3 border-top pt-2" style="max-height: 300px; overflow-y: auto">
                                    @forelse($comments as $c)
                                        <div class="small mb-2 {{ $c->parent_external_id ? 'ms-4' : '' }}">
                                            <strong>{{ $c->author_name ?? 'Unknown' }}</strong>
                                            <span class="text-muted">· {{ $c->commented_at?->diffForHumans() }}</span>
                                            @if($c->like_count) <span class="text-muted">· <i class="ti ti-heart"></i> {{ $c->like_count }}</span> @endif
                                            <div>{{ $c->message }}</div>
                                        </div>
                                    @empty
                                        <p class="small text-muted mb-0">Comments haven't been fetched yet.</p>
                                    @endforelse
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12"><div class="card"><div class="card-body text-muted">No posts yet. Click <strong>Fetch data</strong>.</div></div></div>
            @endforelse
        </div>
        <div class="mt-3">{{ $posts->links() }}</div>
    @endif

    {{-- Insights --}}
    @if($tab === 'insights')
        <div class="row g-3">
            @forelse($insightAccounts as $acc)
                <div class="col-12 col-lg-6" wire:key="ins-{{ $acc->id }}">
                    <div class="card h-100">
                        <div class="card-header"><h6 class="mb-0"><i class="ti {{ $platformIcon[$acc->platform] }}"></i> {{ $acc->username ? '@' . $acc->username : $acc->name }}</h6></div>
                        <div class="card-body">
                            <div class="d-flex gap-4 mb-3 flex-wrap">
                                @foreach(['followers' => 'Followers', 'following' => 'Following', 'media_count' => 'Posts', 'fans' => 'Page likes'] as $k => $label)
                                    @if(isset($acc->meta[$k]))
                                        <div><div class="h5 mb-0">{{ number_format($acc->meta[$k]) }}</div><small class="text-muted">{{ $label }}</small></div>
                                    @endif
                                @endforeach
                            </div>
                            <table class="table table-sm mb-0">
                                <thead><tr><th>Metric (last {{ config('meta.history_days') }} days)</th><th class="text-end">Total</th></tr></thead>
                                <tbody>
                                    @forelse($insights[$acc->id] ?? [] as $row)
                                        <tr>
                                            <td>{{ ucfirst(str_replace(['page_', '_'], ['', ' '], $row->metric)) }} <small class="text-muted">({{ $row->days }} days)</small></td>
                                            <td class="text-end">{{ number_format($row->total) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="2" class="text-muted">No insights fetched yet.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12"><div class="card"><div class="card-body text-muted">Insights come from Facebook Pages and Instagram accounts.</div></div></div>
            @endforelse

            @if($topPosts->isNotEmpty())
                <div class="col-12">
                    <div class="card">
                        <div class="card-header"><h6 class="mb-0">Top posts by engagement</h6></div>
                        <div class="table-responsive">
                            <table class="table mb-0">
                                <thead><tr><th>Post</th><th>Date</th><th class="text-end">Likes</th><th class="text-end">Comments</th><th class="text-end">Shares</th></tr></thead>
                                <tbody>
                                    @foreach($topPosts as $p)
                                        <tr>
                                            <td><i class="ti {{ $platformIcon[$p->account->platform] }}"></i>
                                                @if($p->permalink)<a href="{{ $p->permalink }}" target="_blank" rel="noopener">@endif
                                                {{ \Illuminate\Support\Str::limit($p->message, 60) ?: '(no caption)' }}
                                                @if($p->permalink)</a>@endif
                                            </td>
                                            <td class="text-nowrap">{{ $p->posted_at?->format('d M Y') }}</td>
                                            <td class="text-end">{{ number_format($p->like_count) }}</td>
                                            <td class="text-end">{{ number_format($p->comment_count) }}</td>
                                            <td class="text-end">{{ number_format($p->share_count) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    @endif

    {{-- Audience --}}
    @if($tab === 'audience')
        @php
            $igAudiences = ['follower_demographics' => 'Followers', 'engaged_audience_demographics' => 'Engaged audience'];
            $dimensionLabels = ['age' => 'Age', 'gender' => 'Gender', 'country' => 'Top countries', 'city' => 'Top cities'];
        @endphp
        @if($audienceAccounts->contains('platform', 'instagram'))
            <div class="d-flex align-items-center gap-2 mb-3">
                <small class="text-muted">Instagram audience:</small>
                <div class="btn-group btn-group-sm">
                    @foreach($igAudiences as $metric => $label)
                        <button type="button" wire:click="setAudienceMetric('{{ $metric }}')" class="btn {{ $audienceMetric === $metric ? 'btn-primary' : 'btn-outline-primary' }}">{{ $label }}</button>
                    @endforeach
                </div>
            </div>
        @endif
        @forelse($audienceAccounts as $acc)
            @php
                $byDimension = $demographics[$acc->id] ?? collect();
                $asOf = $byDimension->collapse()->max('updated_at');
            @endphp
            <div class="card mb-3" wire:key="aud-{{ $acc->id }}-{{ $audienceMetric }}">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h6 class="mb-0"><i class="ti {{ $platformIcon[$acc->platform] }}"></i> {{ $acc->username ? '@' . $acc->username : $acc->name }}</h6>
                    <small class="text-muted">
                        @if($acc->platform === 'facebook')
                            Followers by location
                        @else
                            {{ $igAudiences[$audienceMetric] }}{{ $audienceMetric === 'engaged_audience_demographics' ? ' (people who interacted this month)' : '' }}
                        @endif
                        @if($asOf) · as of {{ \Illuminate\Support\Carbon::parse($asOf)->format('d M Y') }} @endif
                    </small>
                </div>
                <div class="card-body">
                    @if($byDimension->isEmpty())
                        <p class="text-muted mb-0">
                            No audience data yet. Click <strong>Fetch data</strong>.
                            @if($acc->platform === 'instagram')
                                Meta only shares this for accounts with at least 100 followers (or 100 engagements this month for the engaged audience).
                            @endif
                        </p>
                    @else
                        <div class="row g-4">
                            @foreach($dimensionLabels as $dim => $label)
                                @continue(!$byDimension->has($dim))
                                @php
                                    $total = $byDimension[$dim]->sum('value') ?: 1;
                                    $rows = $dim === 'age' ? $byDimension[$dim]->sortBy('segment') : $byDimension[$dim]->take(10);
                                @endphp
                                <div class="col-12 col-md-6 col-xl-3">
                                    <h6 class="mb-2">{{ $label }}</h6>
                                    @foreach($rows as $row)
                                        <div class="mb-2">
                                            <div class="d-flex justify-content-between small">
                                                <span class="text-truncate me-2">{{ \App\Models\Social\SocialDemographic::label($dim, $row->segment) }}</span>
                                                <span class="text-nowrap">{{ number_format($row->value) }} <span class="text-muted">· {{ round($row->value / $total * 100) }}%</span></span>
                                            </div>
                                            <div class="progress" style="height: 6px">
                                                <div class="progress-bar" role="progressbar" style="width: {{ round($row->value / $total * 100, 1) }}%"></div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                        <p class="small text-muted mt-3 mb-0">
                            @if($acc->platform === 'facebook')
                                Meta no longer shares the age and gender of Facebook Page followers.
                            @else
                                Meta lists at most 45 countries / cities and leaves out people it has no data for, so totals can be below the follower count.
                            @endif
                        </p>
                    @endif
                </div>
            </div>
        @empty
            <div class="card"><div class="card-body text-muted">Audience data comes from Facebook Pages and Instagram accounts.</div></div>
        @endforelse
    @endif

    {{-- Messages --}}
    @if($tab === 'messages')
        <div class="row g-3">
            <div class="col-12 col-md-5">
                <div class="card">
                    <div class="list-group list-group-flush">
                        @forelse($conversations as $conv)
                            <button type="button" wire:click="openChat({{ $conv->id }})" wire:key="conv-{{ $conv->id }}"
                                class="list-group-item list-group-item-action {{ $openConversation === $conv->id ? 'active' : '' }}">
                                <div class="d-flex justify-content-between">
                                    <strong><i class="ti {{ $platformIcon[$conv->account->platform] }}"></i> {{ $conv->participant_name ?? 'Unknown' }}</strong>
                                    <small>{{ $conv->last_message_at?->diffForHumans() }}</small>
                                </div>
                                <small class="text-truncate d-block">{{ $conv->snippet }}</small>
                            </button>
                        @empty
                            <div class="list-group-item text-muted">No conversations fetched yet.</div>
                        @endforelse
                    </div>
                </div>
                <div class="mt-3">{{ $conversations->links() }}</div>
            </div>
            <div class="col-12 col-md-7">
                <div class="card">
                    <div class="card-body" style="max-height: 600px; overflow-y: auto">
                        @if($chat)
                            <h6 class="mb-3">{{ $chat->participant_name }}</h6>
                            @foreach($chat->messages as $m)
                                <div class="d-flex mb-2 {{ $m->from_page ? 'justify-content-end' : '' }}">
                                    <div class="p-2 rounded {{ $m->from_page ? 'bg-primary text-white' : 'bg-label-secondary' }}" style="max-width: 80%">
                                        <div>{{ $m->message ?: '(attachment)' }}</div>
                                        <small class="opacity-75">{{ $m->sent_at?->format('d M H:i') }}</small>
                                    </div>
                                </div>
                            @endforeach
                            <p class="small text-muted mt-3 mb-0">Showing up to the 50 most recent messages of this conversation. Read-only.</p>
                        @else
                            <p class="text-muted mb-0">Select a conversation.</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Boosts --}}
    @if($tab === 'boosts')
        <div class="row g-3 mb-3">
            @foreach(['spend' => 'Spend', 'impressions' => 'Impressions', 'reach' => 'Reach', 'clicks' => 'Clicks'] as $k => $label)
                <div class="col-6 col-md-3">
                    <div class="card"><div class="card-body">
                        <small class="text-muted">{{ $label }}</small>
                        <div class="h4 mb-0">{{ $k === 'spend' ? number_format((float) $adTotals->spend, 2) : number_format((int) $adTotals->$k) }}</div>
                    </div></div>
                </div>
            @endforeach
        </div>

        {{-- Results split by audience segment, placement or device --}}
        @php
            $breakdownLabels = ['age_gender' => 'Age & gender', 'country' => 'Country', 'placement' => 'Placement', 'device' => 'Device'];
            $spendTotal = (float) $breakdownRows->sum('spend') ?: 1;
        @endphp
        <div class="card mb-3">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h6 class="mb-0">Breakdown</h6>
                    <small class="text-muted">
                        @if($breakdownAdModel)
                            Ad: <strong>{{ $breakdownAdModel->name }}</strong> ·
                            <a href="#" wire:click.prevent="toggleAdBreakdown({{ $breakdownAdModel->id }})">show all ads</a>
                        @else
                            All ads, last {{ config('meta.history_days') }} days. Use <i class="ti ti-chart-pie"></i> on an ad to see it alone.
                        @endif
                    </small>
                </div>
                <ul class="nav nav-pills">
                    @foreach(array_keys(config('meta.ad_breakdowns')) as $name)
                        <li class="nav-item">
                            <button type="button" wire:click="setBreakdown('{{ $name }}')" class="nav-link py-1 px-3 {{ $breakdown === $name ? 'active' : '' }}">
                                {{ $breakdownLabels[$name] ?? ucfirst(str_replace('_', ' ', $name)) }}
                            </button>
                        </li>
                    @endforeach
                </ul>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Segment</th>
                            <th class="text-end">Spend</th>
                            <th style="min-width: 120px">Share of spend</th>
                            <th class="text-end">Impr.</th>
                            @if($breakdownAdModel)<th class="text-end">Reach</th>@endif
                            <th class="text-end">Clicks</th>
                            <th class="text-end">CTR</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($breakdownRows as $row)
                            <tr>
                                <td>{{ \App\Models\Social\SocialAdBreakdown::label($breakdown, $row->segment) }}</td>
                                <td class="text-end">{{ number_format((float) $row->spend, 2) }}</td>
                                <td class="align-middle">
                                    <div class="progress" style="height: 6px">
                                        <div class="progress-bar bg-warning" role="progressbar" style="width: {{ round((float) $row->spend / $spendTotal * 100, 1) }}%"></div>
                                    </div>
                                </td>
                                <td class="text-end">{{ number_format((int) $row->impressions) }}</td>
                                @if($breakdownAdModel)<td class="text-end">{{ number_format((int) $row->reach) }}</td>@endif
                                <td class="text-end">{{ number_format((int) $row->clicks) }}</td>
                                <td class="text-end">{{ $row->impressions ? number_format($row->clicks / $row->impressions * 100, 2) . '%' : '–' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-muted">No breakdown fetched yet. Click Fetch data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @unless($breakdownAdModel)
                <div class="card-footer small text-muted py-2">Reach is shown for one ad at a time: the same person can see several ads, so it can't be added up.</div>
            @endunless
        </div>

        <div class="card">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Ad</th><th>Campaign</th><th>Status</th><th>Started</th><th class="text-end">Spend</th><th class="text-end">Impr.</th><th class="text-end">Reach</th><th class="text-end">Clicks</th><th></th></tr></thead>
                    <tbody>
                        @forelse($ads as $ad)
                            <tr wire:key="ad-{{ $ad->id }}" class="{{ $breakdownAd === $ad->id ? 'table-active' : '' }}">
                                <td>{{ $ad->name }}</td>
                                <td>{{ $ad->campaign_name }} @if($ad->objective)<br><small class="text-muted">{{ str_replace('_', ' ', strtolower($ad->objective)) }}</small>@endif</td>
                                <td><span class="badge {{ $ad->effective_status === 'ACTIVE' ? 'bg-label-success' : 'bg-label-secondary' }}">{{ str_replace('_', ' ', strtolower($ad->effective_status ?? $ad->status)) }}</span></td>
                                <td class="text-nowrap">{{ $ad->started_at?->format('d M Y') }}</td>
                                <td class="text-end">{{ number_format((float) $ad->spend, 2) }} {{ $ad->account->meta['currency'] ?? '' }}</td>
                                <td class="text-end">{{ number_format($ad->impressions) }}</td>
                                <td class="text-end">{{ number_format($ad->reach) }}</td>
                                <td class="text-end">{{ number_format($ad->clicks) }}</td>
                                <td class="text-end">
                                    <button type="button" wire:click="toggleAdBreakdown({{ $ad->id }})" class="btn btn-sm btn-icon {{ $breakdownAd === $ad->id ? 'btn-primary' : 'btn-label-secondary' }}" title="Breakdown for this ad">
                                        <i class="ti ti-chart-pie"></i>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="text-muted">No boosts fetched. Link an ad account when connecting, then click Fetch data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-3">{{ $ads->links() }}</div>
    @endif

    </div>
    @endif
    @endif

    @script
        @include('livewire.deleteConfirm')
    @endscript
</div>
