@php
    // Dokumen PDF berdiri sendiri (tanpa CSS aplikasi): palet setara token tema terang.
    $palette = [
        ['#c62828', '#fdecec'],
        ['#1d4ed8', '#e8effd'],
        ['#166534', '#e5f3ea'],
        ['#92400e', '#fdf0dc'],
        ['#475569', '#eef1f5'],
    ];
    $tone = fn (int $subjectId): array => $palette[$subjectId % count($palette)];
    $hm = fn (string $time): string => substr($time, 0, 5);
    $duration = fn (int $minutes): string => intdiv($minutes, 60).' jam'.($minutes % 60 ? ' '.($minutes % 60).' menit' : '');
    $detail = function ($schedule) use ($view): string {
        $assignment = $schedule->assignment;

        return match ($view) {
            'teacher' => $assignment->studyGroup->name,
            'room' => $assignment->studyGroup->name.' · '.$assignment->teacher->name,
            default => $assignment->teacher->name,
        };
    };
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Jadwal {{ $targetLabel }} · {{ $term->name }} {{ $term->semester }}</title>
    <style>
        @page { margin: 16mm 12mm 14mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8.5pt; color: #0f172a; }
        .kop { border-bottom: 2px solid #c62828; padding-bottom: 6px; margin-bottom: 10px; }
        .kop h1 { margin: 0; font-size: 14pt; }
        .kop p { margin: 2px 0 0; font-size: 8.5pt; color: #475569; }
        .meta { width: 100%; margin-bottom: 8px; border-collapse: collapse; }
        .meta td { padding: 2px 0; font-size: 9pt; }
        .meta .label { width: 70px; color: #475569; }
        table.grid { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.grid th { background: #f1f5f9; border: 1px solid #cbd5e1; padding: 5px 4px; font-size: 8.5pt; text-align: center; }
        table.grid th.session { width: 62px; }
        table.grid td { border: 1px solid #cbd5e1; padding: 3px; vertical-align: top; height: 52px; }
        table.grid td.session { background: #f1f5f9; text-align: center; font-weight: bold; }
        table.grid td.session span { display: block; font-weight: normal; color: #475569; font-size: 7.5pt; }
        .class { padding: 3px 4px; margin-bottom: 3px; }
        .class b { display: block; font-size: 8.5pt; }
        .class small { display: block; color: #475569; font-size: 7.5pt; }
        h2 { font-size: 10pt; margin: 14px 0 5px; }
        table.legend { width: 100%; border-collapse: collapse; }
        table.legend td { padding: 3px 4px; font-size: 8pt; border-bottom: 1px solid #e2e8f0; }
        .dot { display: inline-block; width: 8px; height: 8px; margin-right: 4px; }
        .foot { margin-top: 12px; font-size: 7.5pt; color: #64748b; }
    </style>
</head>
<body>
    <div class="kop">
        <h1>{{ $tenant->name }}</h1>
        <p>Jadwal Kuliah Mingguan @unless ($term->is_active)· Arsip semester @endunless</p>
    </div>

    <table class="meta">
        <tr><td class="label">{{ $viewLabel }}</td><td><b>{{ $targetLabel }}</b></td><td class="label">Semester</td><td><b>{{ $term->name }} · {{ $term->semester }}</b></td></tr>
        <tr>
            <td class="label">Pertemuan</td><td>{{ $stats['meetings'] }} per minggu · {{ $stats['courses'] }} mata kuliah</td>
            <td class="label">Periode</td><td>{{ $term->starts_at->translatedFormat('j F Y') }} – {{ $term->ends_at->translatedFormat('j F Y') }}</td>
        </tr>
    </table>

    <table class="grid">
        <thead>
            <tr>
                <th class="session">Sesi</th>
                @foreach ($days as $dayName)<th>{{ $dayName }}</th>@endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($periods as $periodNumber => $period)
                <tr>
                    <td class="session">{{ $periodNumber }}<span>{{ $hm($period->starts_at) }}–{{ $hm($period->ends_at) }}</span></td>
                    @foreach ($days as $dayNumber => $dayName)
                        <td>
                            @foreach ($grid->get($periodNumber.'-'.$dayNumber, collect()) as $schedule)
                                @php [$accent, $tint] = $tone($schedule->assignment->subject_id); @endphp
                                <div class="class" style="border-left: 3px solid {{ $accent }}; background: {{ $tint }};">
                                    <b>{{ $schedule->assignment->subject->name }}</b>
                                    <small>{{ $detail($schedule) }}</small>
                                    @if ($schedule->room)<small>{{ $schedule->room->name }}</small>@endif
                                </div>
                            @endforeach
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Mata kuliah</h2>
    <table class="legend">
        @foreach ($subjects as $assignment)
            @php [$accent] = $tone($assignment->subject_id); @endphp
            <tr>
                <td style="width: 38%;"><span class="dot" style="background: {{ $accent }};"></span>{{ $assignment->subject->name }}</td>
                <td style="width: 12%;">{{ $assignment->subject->code }}</td>
                <td style="width: 10%;">@if ($assignment->subject->credits){{ $assignment->subject->credits }} SKS @endif</td>
                <td>{{ $view === 'teacher' ? $assignment->studyGroup->name : $assignment->teacher->name }}</td>
            </tr>
        @endforeach
    </table>

    <p class="foot">Total tatap muka {{ $duration($stats['minutes']) }} per minggu. Dicetak {{ now()->translatedFormat('j F Y, H.i') }} oleh {{ $printedBy }} · Aksaraloka Academic.</p>
</body>
</html>
