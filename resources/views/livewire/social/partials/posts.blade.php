<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <div class="relative w-full sm:max-w-sm">
        <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
        <input type="search" wire:model.live.debounce.400ms="postSearch" class="input pl-9" placeholder="Search captions" aria-label="Search captions">
    </div>
    <div class="flex items-center gap-3">
        <span class="text-sm text-slate-500 dark:text-slate-400">
            {{ number_format($posts->total()) }}{{ trim($postSearch) !== '' ? ' of ' . number_format($postTotal) : '' }} {{ str('post')->plural($posts->total()) }}
        </span>
        <label for="post-sort" class="sr-only">Sort posts</label>
        <select id="post-sort" wire:model.live="postSort" class="select w-auto py-1.5">
            <option value="newest">Newest first</option>
            <option value="oldest">Oldest first</option>
            <option value="engagement">Most engagement</option>
            <option value="comments">Most comments</option>
        </select>
    </div>
</div>

@if($posts->isEmpty())
    <div class="card">
        @if(trim($postSearch) !== '')
            <x-ui.empty icon="search" title="No posts match “{{ $postSearch }}”">Try another word, or clear the search.</x-ui.empty>
        @else
            <x-ui.empty icon="photo" title="No posts yet">Posts from the last {{ config('meta.history_days') }} days appear after you fetch data.</x-ui.empty>
        @endif
    </div>
@else
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4" wire:loading.class="opacity-50" wire:target="postSearch">
        @foreach($posts as $post)
            <article class="card group flex flex-col overflow-hidden" wire:key="post-{{ $post->id }}">
                <button type="button" wire:click="openPostDetail({{ $post->id }})" class="relative block aspect-square w-full overflow-hidden bg-slate-100 dark:bg-slate-800" aria-label="Open post from {{ $post->posted_at?->format('j M Y') }}">
                    <x-icon name="photo" class="absolute inset-0 m-auto size-8 text-slate-300 dark:text-slate-600" />
                    @if($post->thumbnail_url || $post->media_url)
                        <img src="{{ $post->thumbnail_url ?: $post->media_url }}" alt="" loading="lazy" class="relative size-full object-cover transition-transform duration-300 group-hover:scale-[1.03]" onerror="this.remove()">
                    @endif
                    <span class="absolute left-2 top-2 flex items-center gap-1 rounded-full bg-white/90 px-2 py-0.5 text-[11px] font-medium text-slate-700 shadow-sm backdrop-blur dark:bg-slate-900/80 dark:text-slate-200">
                        <x-icon :name="$platforms[$post->account->platform]['icon']" class="size-3.5" />
                        {{ $post->type_label ?? $platforms[$post->account->platform]['label'] }}
                    </span>
                </button>
                <div class="flex flex-1 flex-col p-4">
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        <span class="font-medium text-slate-700 dark:text-slate-300">{{ $post->account->display_name }}</span> · {{ $post->posted_at?->format('j M Y') }}
                    </p>
                    <p class="mt-1.5 line-clamp-3 flex-1 text-sm text-slate-700 dark:text-slate-300">{{ $post->message ?: '(no caption)' }}</p>
                    <div class="mt-3 flex items-center gap-4 border-t border-slate-100 pt-3 text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400">
                        <span class="flex items-center gap-1" title="Likes / reactions"><x-icon name="heart" class="size-4" /> {{ $fmt($post->like_count) }}</span>
                        <span class="flex items-center gap-1" title="Comments"><x-icon name="message-circle" class="size-4" /> {{ $fmt($post->comment_count) }}</span>
                        @if($post->shares_total)
                            <span class="flex items-center gap-1" title="Shares"><x-icon name="share-3" class="size-4" /> {{ $fmt($post->shares_total) }}</span>
                        @endif
                        @if($post->reach !== null)
                            <span class="ml-auto flex items-center gap-1" title="Reach"><x-icon name="eye" class="size-4" /> {{ $fmt($post->reach) }}</span>
                        @endif
                    </div>
                </div>
            </article>
        @endforeach
    </div>

    <div class="mt-6">{{ $posts->links() }}</div>
@endif
