@php
    $igAudiences = ['follower_demographics' => 'Followers', 'engaged_audience_demographics' => 'Engaged audience'];
    $label = fn ($dim, $seg) => \App\Models\Social\SocialDemographic::label($dim, $seg);
    $genderColors = ['Women' => 'bg-[#E1306C]', 'Men' => 'bg-brand-500', 'Unknown' => 'bg-slate-300 dark:bg-slate-600'];
@endphp

@if($audienceAccounts->contains('platform', 'instagram'))
    <div class="mb-5 flex flex-wrap items-center gap-3">
        <span class="text-sm text-slate-500 dark:text-slate-400">Instagram audience</span>
        <div class="segmented" role="group" aria-label="Instagram audience">
            @foreach($igAudiences as $metric => $name)
                <button type="button" wire:click="setAudienceMetric('{{ $metric }}')" aria-pressed="{{ $audienceMetric === $metric ? 'true' : 'false' }}">{{ $name }}</button>
            @endforeach
        </div>
        @if($audienceMetric === 'engaged_audience_demographics')
            <span class="text-xs text-slate-400">People who liked, commented, saved or shared this month</span>
        @endif
    </div>
@endif

<div class="space-y-6">
    @forelse($audienceAccounts as $acc)
        @php
            $byDim = $demographics[$acc->id] ?? collect();
            $asOf = $byDim->collapse()->max('updated_at');
        @endphp
        <section class="card" wire:key="aud-{{ $acc->id }}-{{ $audienceMetric }}">
            <div class="card-header">
                <div class="flex items-center gap-3">
                    @include('livewire.social.partials.avatar', ['acc' => $acc, 'size' => 'md'])
                    <div>
                        <h2 class="card-title">{{ $acc->display_name }}</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            {{ $acc->platform === 'facebook' ? 'Followers by location' : $igAudiences[$audienceMetric] }}
                            @if($asOf) · as of {{ \Illuminate\Support\Carbon::parse($asOf)->format('j M Y') }} @endif
                        </p>
                    </div>
                </div>
            </div>

            @if($byDim->isEmpty())
                <x-ui.empty icon="users-group" title="No audience data yet" compact>
                    @if($acc->platform === 'instagram')
                        Meta only shares this for accounts with at least 100 followers (or 100 engagements this month for the engaged audience).
                    @else
                        It appears after the next fetch.
                    @endif
                </x-ui.empty>
            @else
                <div @class([
                    'grid gap-8 p-5 md:grid-cols-2',
                    'xl:grid-cols-4' => $byDim->keys()->intersect(['gender', 'age', 'country', 'city'])->count() > 2,
                ])>
                    {{-- Gender: one split bar --}}
                    @if($byDim->has('gender'))
                        @php
                            $g = $byDim['gender']->groupBy(fn ($r) => $label('gender', $r->segment))->map->sum('value');
                            $gTotal = $g->sum() ?: 1;
                        @endphp
                        <div>
                            <h3 class="mb-3 text-sm font-semibold">Gender</h3>
                            <div class="flex h-3 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                @foreach($g as $name => $value)
                                    <span class="{{ $genderColors[$name] ?? 'bg-slate-400' }}" style="width: {{ round($value / $gTotal * 100, 1) }}%"></span>
                                @endforeach
                            </div>
                            <ul class="mt-3 space-y-1.5">
                                @foreach($g as $name => $value)
                                    <li class="flex items-center justify-between text-sm">
                                        <span class="flex items-center gap-2"><span class="size-2.5 rounded-full {{ $genderColors[$name] ?? 'bg-slate-400' }}"></span>{{ $name }}</span>
                                        <span class="tabular-nums text-slate-900 dark:text-white">{{ round($value / $gTotal * 100) }}% <span class="text-xs text-slate-400">· {{ $fmt($value) }}</span></span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- Age, countries, cities: bars --}}
                    @foreach(['age' => 'Age', 'country' => 'Top countries', 'city' => 'Top cities'] as $dim => $title)
                        @continue(!$byDim->has($dim))
                        @php
                            $total = $byDim[$dim]->sum('value') ?: 1;
                            $rows = $dim === 'age' ? $byDim[$dim]->sortBy('segment') : $byDim[$dim]->take(8);
                            $max = $rows->max('value') ?: 1;
                        @endphp
                        <div>
                            <h3 class="mb-3 text-sm font-semibold">{{ $title }}</h3>
                            <ul class="space-y-2.5">
                                @foreach($rows as $row)
                                    <li>
                                        <div class="flex justify-between gap-2 text-sm">
                                            <span class="truncate text-slate-600 dark:text-slate-300">{{ $label($dim, $row->segment) }}</span>
                                            <span class="shrink-0 tabular-nums text-slate-900 dark:text-white">{{ round($row->value / $total * 100) }}%</span>
                                        </div>
                                        <div class="meter mt-1" title="{{ number_format($row->value) }}"><span style="width: {{ round($row->value / $max * 100, 1) }}%"></span></div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
                <p class="border-t border-slate-100 px-5 py-3 text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400">
                    @if($acc->platform === 'facebook')
                        Meta no longer shares the age and gender of Facebook Page followers.
                    @else
                        Meta lists at most 45 countries or cities and leaves out people it has no data for, so totals can be below the follower count.
                    @endif
                </p>
            @endif
        </section>
    @empty
        <div class="card"><x-ui.empty icon="users-group" title="No Facebook or Instagram account selected" /></div>
    @endforelse
</div>
