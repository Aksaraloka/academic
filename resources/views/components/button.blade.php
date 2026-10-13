@props(['variant' => 'primary', 'size' => 'md', 'href' => null, 'type' => 'button', 'icon' => null])
@php
    $variants = [
        'primary' => 'bg-primary text-primary-fg hover:opacity-90',
        'secondary' => 'border border-border bg-surface text-text hover:bg-surface-muted',
        'ghost' => 'text-text hover:bg-surface-muted',
        'danger' => 'bg-danger text-white hover:opacity-90 dark:text-black',
    ];
    $sizes = [
        'sm' => 'min-h-9 px-3 text-sm',
        'md' => 'min-h-11 px-4 text-base',
        'lg' => 'min-h-12 px-6 text-base',
    ];
    $classes = 'inline-flex cursor-pointer items-center justify-center gap-2 rounded-lg font-medium transition disabled:cursor-not-allowed disabled:opacity-60 '.$variants[$variant].' '.$sizes[$size];
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<x-icon :name="$icon" class="size-4" />@endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<x-icon :name="$icon" class="size-4" />@endif
        {{ $slot }}
    </button>
@endif
