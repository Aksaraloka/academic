@props(['slices', 'size' => 'size-40', 'legend' => true, 'title' => 'Diagram lingkaran'])
@php
    $fills = ['success' => 'fill-success', 'info' => 'fill-info', 'warning' => 'fill-warning', 'danger' => 'fill-danger', 'primary' => 'fill-primary', 'neutral' => 'fill-text-muted'];
    $swatches = ['success' => 'bg-success', 'info' => 'bg-info', 'warning' => 'bg-warning', 'danger' => 'bg-danger', 'primary' => 'bg-primary', 'neutral' => 'bg-text-muted'];

    $slices = collect($slices)->values();
    $total = (int) $slices->sum('value');
    $summary = $slices
        ->map(fn (array $slice): string => $slice['label'].' '.$slice['value'].($total > 0 ? ' ('.round($slice['value'] / $total * 100).'%)' : ''))
        ->implode(', ');

    $shapes = [];
    $angle = -M_PI / 2;

    foreach ($slices as $slice) {
        if ($slice['value'] <= 0 || $total === 0) {
            continue;
        }

        $fraction = $slice['value'] / $total;

        if ($fraction >= 0.9999) {
            $shapes[] = ['full' => true, 'variant' => $slice['variant']];

            continue;
        }

        $end = $angle + $fraction * 2 * M_PI;
        $shapes[] = [
            'full' => false,
            'variant' => $slice['variant'],
            'd' => sprintf(
                'M50 50 L%.3f %.3f A48 48 0 %d 1 %.3f %.3f Z',
                50 + 48 * cos($angle), 50 + 48 * sin($angle),
                $fraction > 0.5 ? 1 : 0,
                50 + 48 * cos($end), 50 + 48 * sin($end),
            ),
        ];
        $angle = $end;
    }
@endphp
<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-4']) }}>
    <svg viewBox="0 0 100 100" data-pop class="{{ $size }} shrink-0" role="img" aria-label="{{ $title }}: {{ $total > 0 ? $summary : 'belum ada data' }}">
        <title>{{ $title }}</title>
        @if ($total === 0)
            <circle cx="50" cy="50" r="48" class="fill-surface-muted stroke-border" stroke-width="1" />
        @else
            @foreach ($shapes as $shape)
                @if ($shape['full'])
                    <circle cx="50" cy="50" r="48" class="{{ $fills[$shape['variant']] }}" />
                @else
                    <path d="{{ $shape['d'] }}" class="{{ $fills[$shape['variant']] }} stroke-surface" stroke-width="1.5" stroke-linejoin="round" />
                @endif
            @endforeach
        @endif
    </svg>

    @if ($legend)
        <ul class="min-w-0 flex-1 space-y-1.5 text-sm">
            @foreach ($slices as $slice)
                <li class="flex items-center gap-2" wire:key="pie-{{ $loop->index }}">
                    <span class="size-3 shrink-0 rounded-sm {{ $swatches[$slice['variant']] }}" aria-hidden="true"></span>
                    <span class="min-w-0 flex-1 truncate">{{ $slice['label'] }}</span>
                    <span class="font-semibold tabular-nums">{{ $slice['value'] }}</span>
                    <span class="w-10 text-right text-xs tabular-nums text-text-muted">{{ $total > 0 ? round($slice['value'] / $total * 100).'%' : '–' }}</span>
                </li>
            @endforeach
        </ul>
    @endif
</div>
