@props(['label', 'value', 'note' => null, 'tone' => 'text-text-muted'])
<div {{ $attributes->merge(['class' => 'rounded-card border border-border bg-surface p-4']) }}>
    <p class="text-xs font-medium text-text-muted">{{ $label }}</p>
    <p class="mt-1 text-2xl font-bold leading-none tabular-nums sm:text-3xl" data-countup>{{ $value }}</p>
    @if ($note)
        <p class="mt-2 text-xs font-semibold {{ $tone }}">{{ $note }}</p>
    @endif
</div>
