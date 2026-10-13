@props(['variant' => 'neutral', 'icon' => null])
@php
    $variants = [
        'neutral' => 'bg-surface-muted text-text-muted',
        'primary' => 'bg-primary/15 text-primary',
        'success' => 'bg-success/15 text-success',
        'warning' => 'bg-warning/15 text-warning',
        'danger' => 'bg-danger/15 text-danger',
        'info' => 'bg-info/15 text-info',
    ];
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold '.$variants[$variant]]) }}>
    @if ($icon)<x-icon :name="$icon" solid />@endif
    {{ $slot }}
</span>
