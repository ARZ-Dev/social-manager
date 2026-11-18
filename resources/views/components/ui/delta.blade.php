{{-- Change vs the previous period, e.g. "+12.5%". Renders nothing without a comparison. --}}
@props(['value'])
@if(!is_null($value))
    <span {{ $attributes->class([
        'inline-flex items-center gap-0.5 text-xs font-semibold tabular-nums',
        'text-emerald-600 dark:text-emerald-400' => $value >= 0,
        'text-red-600 dark:text-red-400' => $value < 0,
    ]) }}>
        <x-icon :name="$value >= 0 ? 'trending-up' : 'trending-down'" class="size-3.5" />
        {{ $value > 0 ? '+' : '' }}{{ rtrim(rtrim(number_format($value, 1), '0'), '.') }}%
    </span>
@endif
