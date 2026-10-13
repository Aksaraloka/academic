@props(['label', 'value', 'max', 'empty' => 'Belum ada data'])
@php
    $percent = $max > 0 ? (int) round($value / $max * 100) : null;
@endphp
<div>
    @if ($percent === null)
        <p class="text-xs font-medium text-text-muted">{{ $empty }}</p>
    @else
        <div class="mb-1 flex items-center justify-between gap-2">
            <span class="text-xs font-medium text-text-muted">{{ $label }}</span>
            <span class="text-xs font-semibold tabular-nums">{{ $percent }}% ({{ $value }}/{{ $max }})</span>
        </div>
        <div class="h-2 rounded-full bg-surface-muted" role="progressbar" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ $label }}">
            <div class="h-2 rounded-full {{ $percent >= 100 ? 'bg-success' : 'bg-primary' }}" style="width: {{ $percent }}%"></div>
        </div>
    @endif
</div>
