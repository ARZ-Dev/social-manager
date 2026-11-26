{{-- Inside a chat bubble: attached file, text, time and (outgoing WhatsApp) delivery status. Expects $m. --}}
@php
    $media = $m->media ?? [];
    $hasFile = $m->type && $m->type !== 'text';
    $src = $hasFile && $m->hasViewableMedia() ? route('social.media', $m) : null;
    $mime = strtolower((string) ($media['mime_type'] ?? ''));
    // BSB fills voice notes with "🎤 *Voice Message*": the player says it already.
    $text = $m->type === 'audio' ? null : $m->message;
    $status = [
        'sent' => ['check', 'Sent'],
        'delivered' => ['checks', 'Delivered'],
        'read' => ['checks', 'Read'],
        'failed' => ['alert-circle', 'Failed'],
    ][$m->status] ?? null;
@endphp

@if($hasFile)
    <div class="mb-1">
        @if($src && $m->type === 'image' && !str_contains($mime, 'svg'))
            <a href="{{ $src }}" target="_blank" rel="noopener" class="block">
                <img src="{{ $src }}" alt="Photo from {{ $m->from_page ? 'you' : ($m->from_name ?: 'the customer') }}" loading="lazy" class="max-h-64 rounded-lg object-cover">
            </a>
        @elseif($src && $m->type === 'audio')
            <audio controls preload="none" src="{{ $src }}" class="h-10 w-64 max-w-full" aria-label="{{ $m->mediaLabel() }}"></audio>
        @elseif($src && $m->type === 'video')
            <video controls preload="metadata" src="{{ $src }}" class="max-h-64 max-w-full rounded-lg"></video>
        @elseif($src)
            <a href="{{ $src }}" target="_blank" rel="noopener" class="flex items-center gap-2 rounded-lg bg-black/5 px-3 py-2 underline-offset-2 hover:underline dark:bg-white/10">
                <x-icon name="file-text" class="size-5 shrink-0" />
                <span class="truncate">{{ $m->mediaLabel() }}</span>
            </a>
        @else
            <p class="flex items-center gap-1.5 italic opacity-80">
                <x-icon name="file-off" class="size-4 shrink-0" /> {{ $m->mediaLabel() }} · no longer available
            </p>
        @endif
    </div>
@endif

@if($text)
    <p class="whitespace-pre-line break-words">{{ $text }}</p>
@elseif(!$hasFile)
    @if($m->from_page && $m->status)
        {{-- Sent from BSB: its webhook only reports the status --}}
        <p class="italic opacity-80">Message sent from BSB (text not shared)</p>
    @else
        <p>(attachment)</p>
    @endif
@endif

@if($m->status === 'failed' && $m->error)
    <p class="mt-1 text-[11px] opacity-90">{{ $m->error }}</p>
@endif

<p @class(['mt-0.5 flex items-center justify-end gap-1 text-[10px]', 'text-white/70 dark:text-slate-950/60' => $m->from_page, 'text-slate-400' => !$m->from_page])>
    {{ $m->sent_at?->format('H:i') }}
    @if($status)
        <span class="inline-flex items-center gap-0.5 {{ $m->status === 'failed' ? 'font-semibold' : '' }}" title="{{ $status[1] }}">
            · <x-icon :name="$status[0]" class="size-3" aria-label="{{ $status[1] }}" /> {{ $status[1] }}
        </span>
    @endif
</p>
