@php
    $rt = $responseSummary;
    $fmtTime = fn ($s) => \App\Support\ResponseTimes::format($s);
    $rtCards = [
        ['Median first response', $fmtTime($rt['medianFirst']), 'stopwatch', 'Half of the conversations got their first answer faster'],
        ['Average response time', $fmtTime($rt['average']), 'clock-hour-4', $rt['responses'] ? number_format($rt['responses']) . ' ' . str('reply')->plural($rt['responses']) . ' measured' : 'No replies in this period'],
        ['Reply rate', $rt['replyRate'] !== null ? $rt['replyRate'] . '%' : '—', 'message-circle', $rt['conversations'] ? number_format($rt['replied']) . ' of ' . number_format($rt['conversations']) . ' conversations answered' : 'No customer messages in this period'],
        ['Answered within 1 hour', $rt['within1h'] !== null ? $rt['within1h'] . '%' : '—', 'clock', 'Share of all replies'],
    ];
    $bucketMax = max(1, max($rt['buckets']));
@endphp

{{-- Response times --}}
<div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    @foreach($rtCards as [$label, $value, $icon, $note])
        <div class="card p-5">
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ $label }}</p>
                <x-icon :name="$icon" class="size-5 text-slate-300 dark:text-slate-600" />
            </div>
            <p class="mt-2 text-3xl font-semibold tracking-tight text-slate-900 dark:text-white">{{ $value }}</p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $note }}</p>
        </div>
    @endforeach
</div>

<div class="mb-6 grid gap-6 xl:grid-cols-3">
    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">How fast replies go out</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">Every reply, last {{ $days }} days</p>
            </div>
        </div>
        <div class="space-y-3 p-5">
            @foreach($rt['buckets'] as $label => $n)
                <div class="flex items-center gap-3 text-sm">
                    <span class="w-24 shrink-0 text-slate-600 dark:text-slate-300">{{ $label }}</span>
                    <div class="meter flex-1"><span style="width: {{ round($n / $bucketMax * 100, 1) }}%"></span></div>
                    <span class="w-10 shrink-0 text-right tabular-nums text-slate-900 dark:text-white">{{ number_format($n) }}</span>
                </div>
            @endforeach
        </div>
    </div>

    <div class="card overflow-hidden xl:col-span-2">
        <div class="card-header">
            <div>
                <h2 class="card-title">By account</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">Messenger and Instagram Direct, last {{ $days }} days</p>
            </div>
            @if($rt['awaiting'])
                <button type="button" wire:click="setChatFilter('awaiting')" class="badge badge-yellow hover:opacity-80">
                    <x-icon name="message-circle-exclamation" class="size-3.5" /> {{ $rt['awaiting'] }} awaiting a reply
                </button>
            @endif
        </div>
        @if($responseByAccount->isEmpty())
            <x-ui.empty icon="stopwatch" title="Nothing to measure in this period" compact>Response times appear once customers have written in the period.</x-ui.empty>
        @else
            <div class="relative overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Account</th>
                            <th class="!text-right">Conversations</th>
                            <th class="!text-right">Reply rate</th>
                            <th class="!text-right">Median first response</th>
                            <th class="!text-right">Average</th>
                            <th class="!text-right">Awaiting</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($responseByAccount as $row)
                            @php $s = $row['summary']; @endphp
                            <tr wire:key="rt-{{ $row['account']->id }}">
                                <td>
                                    <span class="flex items-center gap-3">
                                        @include('livewire.social.partials.avatar', ['acc' => $row['account'], 'size' => 'sm'])
                                        <span class="font-medium text-slate-900 dark:text-white">{{ $row['account']->display_name }}</span>
                                    </span>
                                </td>
                                <td class="text-right tabular-nums">{{ number_format($s['conversations']) }}</td>
                                <td class="text-right tabular-nums">{{ $s['replyRate'] !== null ? $s['replyRate'] . '%' : '—' }}</td>
                                <td class="text-right tabular-nums">{{ $fmtTime($s['medianFirst']) }}</td>
                                <td class="text-right tabular-nums">{{ $fmtTime($s['average']) }}</td>
                                <td class="text-right tabular-nums">{{ $s['awaiting'] ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        <p class="border-t border-slate-100 px-5 py-2.5 text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400">
            Measured from a customer's first unanswered message to the next reply from the Page or account, using the latest 50 messages fetched per conversation.
        </p>
    </div>
</div>

<div class="card overflow-hidden">
    <div class="grid md:grid-cols-[20rem_1fr] lg:grid-cols-[22rem_1fr]">
        {{-- Conversation list --}}
        <div @class(['flex flex-col border-slate-200 md:border-r dark:border-slate-800', 'hidden md:flex' => $chat])>
            <div class="border-b border-slate-200 p-3 dark:border-slate-800">
                <div class="relative">
                    <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                    <input type="search" wire:model.live.debounce.300ms="chatSearch" class="input pl-9" placeholder="Search people or messages" aria-label="Search conversations">
                </div>
                <div class="segmented mt-2 w-full" role="group" aria-label="Show">
                    <button type="button" class="flex-1" wire:click="setChatFilter('')" aria-pressed="{{ $chatFilter === '' ? 'true' : 'false' }}">All</button>
                    <button type="button" class="flex-1" wire:click="setChatFilter('awaiting')" aria-pressed="{{ $chatFilter === 'awaiting' ? 'true' : 'false' }}">
                        Awaiting reply{{ $rt['awaiting'] ? ' · ' . $rt['awaiting'] : '' }}
                    </button>
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
                                @php $st = $responseStats[$conv->id] ?? null; @endphp
                                @if($st && $st['awaitingSince'])
                                    <span class="mt-1 inline-flex items-center gap-1 text-[11px] font-medium text-amber-700 dark:text-amber-400">
                                        <x-icon name="clock-pause" class="size-3" /> Waiting {{ $st['awaitingSince']->diffForHumans(null, true) }}
                                    </span>
                                @elseif($st && $st['first'] !== null)
                                    <span class="mt-1 inline-flex items-center gap-1 text-[11px] text-slate-400">
                                        <x-icon name="stopwatch" class="size-3" /> First reply in {{ \App\Support\ResponseTimes::format($st['first']) }}
                                    </span>
                                @endif
                            </span>
                        </button>
                    </li>
                @empty
                    <li>
                        <x-ui.empty icon="messages" :title="trim($chatSearch) !== '' || $chatFilter ? 'No conversations match' : 'No conversations yet'" compact>
                            @if($chatFilter === 'awaiting')
                                Nobody is waiting for a reply in the last {{ $days }} days.
                            @elseif(trim($chatSearch) === '')
                                Messenger and Instagram conversations appear after you fetch data.
                            @endif
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
