@php
    $post = $postDetail;
    $labels = config('meta.post_metric_labels');
    $metrics = collect($post->insights ?? [])
        ->reject(fn ($v, $k) => $v === null || in_array($k, ['likes', 'comments', 'shares']))
        ->mapWithKeys(fn ($v, $k) => [$labels[$k] ?? \Illuminate\Support\Str::headline($k) => $v]);
    $rate = $post->reach ? round($post->engagement / $post->reach * 100, 1) : null;
@endphp

<x-ui.modal close="closePost" size="5xl" title="Post details">
    <x-slot:header>
        <div class="flex items-center gap-3">
            @include('livewire.social.partials.avatar', ['acc' => $post->account, 'size' => 'md'])
            <div class="min-w-0">
                <p class="truncate text-sm font-semibold text-slate-900 dark:text-white">{{ $post->account->display_name }}</p>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    {{ $post->posted_at?->format('l j F Y, H:i') }}{{ $post->type_label ? ' · ' . $post->type_label : '' }}
                </p>
            </div>
        </div>
    </x-slot:header>

    <div class="grid gap-6 md:grid-cols-5">
        {{-- Media + numbers --}}
        <div class="md:col-span-2">
            <div class="relative aspect-square overflow-hidden rounded-xl bg-slate-100 dark:bg-slate-800">
                <x-icon name="photo" class="absolute inset-0 m-auto size-10 text-slate-300 dark:text-slate-600" />
                @if($post->thumbnail_url || $post->media_url)
                    <img src="{{ $post->thumbnail_url ?: $post->media_url }}" alt="" class="relative size-full object-cover" onerror="this.remove()">
                @endif
            </div>

            <dl class="mt-4 grid grid-cols-3 gap-2 text-center">
                @foreach([['Likes', $post->like_count], ['Comments', $post->comment_count], ['Shares', $post->shares_total]] as [$label, $value])
                    <div class="rounded-lg bg-slate-50 p-3 dark:bg-slate-800/60">
                        <dt class="text-[11px] font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $label }}</dt>
                        <dd class="mt-0.5 text-lg font-semibold tabular-nums text-slate-900 dark:text-white">{{ number_format($value) }}</dd>
                    </div>
                @endforeach
            </dl>

            @if($metrics->isNotEmpty() || $rate !== null)
                <dl class="mt-3 divide-y divide-slate-100 rounded-lg border border-slate-200 text-sm dark:divide-slate-800 dark:border-slate-800">
                    @foreach($metrics as $label => $value)
                        <div class="flex justify-between px-3 py-2">
                            <dt class="text-slate-500 dark:text-slate-400">{{ $label }}</dt>
                            <dd class="font-medium tabular-nums text-slate-900 dark:text-white">{{ number_format((float) $value) }}</dd>
                        </div>
                    @endforeach
                    @if($rate !== null)
                        <div class="flex justify-between px-3 py-2">
                            <dt class="text-slate-500 dark:text-slate-400">Engagement rate</dt>
                            <dd class="font-medium tabular-nums text-slate-900 dark:text-white">{{ $rate }}%</dd>
                        </div>
                    @endif
                </dl>
            @endif

            @if($post->permalink)
                <a href="{{ $post->permalink }}" target="_blank" rel="noopener" class="btn btn-secondary mt-4 w-full">
                    <x-icon name="external-link" class="size-4" /> Open on {{ $platforms[$post->account->platform]['label'] }}
                </a>
            @endif
        </div>

        {{-- Caption + comments --}}
        <div class="md:col-span-3">
            <p class="whitespace-pre-line text-sm leading-relaxed text-slate-700 dark:text-slate-300">{{ $post->message ?: '(no caption)' }}</p>

            <h3 class="mt-6 flex items-center gap-2 border-t border-slate-100 pt-4 text-sm font-semibold dark:border-slate-800">
                Comments <span class="badge badge-gray">{{ number_format($post->comment_count) }}</span>
            </h3>

            <div class="mt-3 space-y-4">
                @forelse($postThreads as $thread)
                    @foreach([$thread['comment'], ...$thread['replies']] as $c)
                        <div @class(['flex gap-3', 'ml-11' => !$loop->first]) wire:key="c-{{ $c->id }}">
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-slate-100 text-[11px] font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-300">
                                {{ strtoupper(mb_substr($c->author_name ?? '?', 0, 1)) }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="rounded-2xl rounded-tl-sm bg-slate-100 px-3 py-2 dark:bg-slate-800">
                                    <p class="text-xs font-semibold text-slate-900 dark:text-white">{{ $c->author_name ?? 'Someone' }}</p>
                                    <p class="whitespace-pre-line break-words text-sm text-slate-700 dark:text-slate-300">{{ $c->message ?: '(sticker or attachment)' }}</p>
                                </div>
                                <p class="mt-1 flex gap-3 px-3 text-[11px] text-slate-500 dark:text-slate-400">
                                    <span title="{{ $c->commented_at?->format('d M Y H:i') }}">{{ $c->commented_at?->diffForHumans() }}</span>
                                    @if($c->like_count) <span class="flex items-center gap-0.5"><x-icon name="heart" class="size-3" /> {{ $c->like_count }}</span> @endif
                                </p>
                            </div>
                        </div>
                    @endforeach
                @empty
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{ $post->comment_count ? 'Comments haven’t been fetched yet. Fetch data to load them.' : 'No comments on this post.' }}
                    </p>
                @endforelse
            </div>
        </div>
    </div>
</x-ui.modal>
