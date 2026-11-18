<div class="card overflow-hidden">
    <div class="grid md:grid-cols-[20rem_1fr] lg:grid-cols-[22rem_1fr]">
        {{-- Conversation list --}}
        <div @class(['flex flex-col border-slate-200 md:border-r dark:border-slate-800', 'hidden md:flex' => $chat])>
            <div class="border-b border-slate-200 p-3 dark:border-slate-800">
                <div class="relative">
                    <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                    <input type="search" wire:model.live.debounce.300ms="chatSearch" class="input pl-9" placeholder="Search people or messages" aria-label="Search conversations">
                </div>
            </div>
            <ul class="max-h-[34rem] flex-1 overflow-y-auto scrollbar-thin" wire:loading.class="opacity-50" wire:target="chatSearch">
                @forelse($conversations as $conv)
                    @php $active = $openConversation === $conv->id; @endphp
                    <li wire:key="conv-{{ $conv->id }}">
                        <button type="button" wire:click="openChat({{ $conv->id }})" @if($active) aria-current="true" @endif
                            @class([
                                'flex w-full gap-3 border-b border-slate-100 px-4 py-3 text-left transition-colors dark:border-slate-800',
                                'bg-brand-50 dark:bg-brand-500/10' => $active,
                                'hover:bg-slate-50 dark:hover:bg-slate-800/40' => !$active,
                            ])>
                            <span class="relative flex size-10 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-300">
                                {{ strtoupper(mb_substr($conv->participant_name ?? '?', 0, 2)) }}
                                <span class="absolute -bottom-0.5 -right-0.5 flex size-4 items-center justify-center rounded-full text-white ring-2 ring-white dark:ring-slate-900 {{ $platforms[$conv->account->platform]['dot'] }}">
                                    <x-icon :name="$platforms[$conv->account->platform]['icon']" class="size-2.5" />
                                </span>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="flex items-baseline justify-between gap-2">
                                    <span class="truncate text-sm font-medium text-slate-900 dark:text-white">{{ $conv->participant_name ?? 'Unknown' }}</span>
                                    <span class="shrink-0 text-[11px] text-slate-400">{{ $conv->last_message_at?->shortRelativeDiffForHumans() }}</span>
                                </span>
                                <span class="mt-0.5 block truncate text-xs text-slate-500 dark:text-slate-400">{{ $conv->snippet ?: '(attachment)' }}</span>
                            </span>
                        </button>
                    </li>
                @empty
                    <li>
                        <x-ui.empty icon="messages" :title="trim($chatSearch) !== '' ? 'No conversations match' : 'No conversations yet'" compact>
                            @if(trim($chatSearch) === '') Messenger and Instagram conversations appear after you fetch data. @endif
                        </x-ui.empty>
                    </li>
                @endforelse
            </ul>
            @if($conversations->hasPages())
                <div class="border-t border-slate-200 p-3 dark:border-slate-800">{{ $conversations->links(data: ['scrollTo' => false]) }}</div>
            @endif
        </div>

        {{-- Thread --}}
        <div @class(['flex min-h-[34rem] flex-col', 'hidden md:flex' => !$chat])>
            @if($chat)
                <div class="flex items-center gap-3 border-b border-slate-200 px-4 py-3 dark:border-slate-800">
                    <button type="button" wire:click="closeChat" class="btn btn-ghost btn-icon btn-sm -ml-1 md:hidden" aria-label="Back to conversations">
                        <x-icon name="arrow-left" class="size-4" />
                    </button>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold text-slate-900 dark:text-white">{{ $chat->participant_name ?? 'Unknown' }}</p>
                        <p class="flex items-center gap-1 text-xs text-slate-500 dark:text-slate-400">
                            <x-icon :name="$platforms[$chat->account->platform]['icon']" class="size-3.5" />
                            {{ $chat->account->platform === 'instagram' ? 'Instagram Direct' : 'Messenger' }} · {{ $chat->account->display_name }}
                        </p>
                    </div>
                </div>

                <div class="flex-1 space-y-4 overflow-y-auto bg-slate-50/60 px-4 py-5 scrollbar-thin dark:bg-slate-950/40" style="max-height: 30rem"
                    wire:key="thread-{{ $chat->id }}" x-data x-init="$el.scrollTop = $el.scrollHeight">
                    @foreach($chatDays as $day => $messages)
                        <div class="flex justify-center">
                            <span class="rounded-full bg-white px-3 py-0.5 text-[11px] font-medium text-slate-500 shadow-sm dark:bg-slate-800 dark:text-slate-400">
                                {{ $day ? \Illuminate\Support\Carbon::parse($day)->calendar(null, ['sameDay' => '[Today]', 'lastDay' => '[Yesterday]', 'lastWeek' => 'dddd', 'sameElse' => 'D MMM YYYY']) : 'Unknown date' }}
                            </span>
                        </div>
                        @foreach($messages as $m)
                            <div @class(['flex', 'justify-end' => $m->from_page]) wire:key="m-{{ $m->id }}">
                                <div @class([
                                    'max-w-[78%] rounded-2xl px-3.5 py-2 text-sm shadow-sm',
                                    'rounded-br-md bg-brand-600 text-white dark:bg-brand-500 dark:text-slate-950' => $m->from_page,
                                    'rounded-bl-md bg-white text-slate-800 dark:bg-slate-800 dark:text-slate-100' => !$m->from_page,
                                ])>
                                    <p class="whitespace-pre-line break-words">{{ $m->message ?: '(attachment)' }}</p>
                                    <p @class(['mt-0.5 text-right text-[10px]', 'text-white/70 dark:text-slate-950/60' => $m->from_page, 'text-slate-400' => !$m->from_page])>{{ $m->sent_at?->format('H:i') }}</p>
                                </div>
                            </div>
                        @endforeach
                    @endforeach
                </div>

                <p class="border-t border-slate-200 px-4 py-2.5 text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400">
                    Read-only. Shows up to the 50 latest messages. Reply from Meta Business Suite.
                </p>
            @else
                <x-ui.empty icon="message-circle" title="Select a conversation" class="my-auto">Pick a conversation to read it here.</x-ui.empty>
            @endif
        </div>
    </div>
</div>
