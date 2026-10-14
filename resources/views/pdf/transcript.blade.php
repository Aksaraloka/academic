@php $num = fn (float $value, int $decimals = 2): string => number_format($value, $decimals, ',', '.'); @endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Transkrip {{ $student->nim }}</title>
    <style>
        @page { margin: 18mm 14mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8.5pt; color: #0f172a; }
        .kop { border-bottom: 2px solid #c62828; padding-bottom: 6px; margin-bottom: 10px; }
        .kop h1 { margin: 0; font-size: 14pt; }
        .kop p { margin: 2px 0 0; color: #475569; }
        h2 { text-align: center; font-size: 12pt; margin: 8px 0 10px; text-transform: uppercase; }
        h3 { font-size: 9.5pt; margin: 10px 0 4px; }
        table.meta { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        table.meta td { padding: 2px 0; }
        table.meta td.label { width: 22%; color: #475569; }
        table.grid { width: 100%; border-collapse: collapse; page-break-inside: auto; }
        table.grid th { background: #f1f5f9; border: 1px solid #cbd5e1; padding: 4px; font-size: 8pt; }
        table.grid td { border: 1px solid #cbd5e1; padding: 3px 4px; }
        tr { page-break-inside: avoid; }
        .r { text-align: right; } .c { text-align: center; }
        .summary { margin-top: 12px; width: 100%; border-collapse: collapse; }
        .summary td { padding: 3px 0; }
        .foot { margin-top: 12px; font-size: 7.5pt; color: #64748b; }
    </style>
</head>
<body>
    <div class="kop">
        <h1>{{ $tenant->name }}</h1>
        <p>Biro Administrasi Akademik dan Kemahasiswaan</p>
    </div>

    <h2>Transkrip Nilai</h2>

    <table class="meta">
        <tr><td class="label">Nama</td><td><b>{{ $student->name }}</b></td><td class="label">Program studi</td><td>{{ $student->program?->label() ?? '-' }}</td></tr>
        <tr><td class="label">NIM</td><td>{{ $student->nim }}</td><td class="label">Fakultas</td><td>{{ $student->program?->faculty?->name ?? '-' }}</td></tr>
        <tr><td class="label">Tempat, tanggal lahir</td><td>{{ trim(($student->birth_place ?? '').($student->birth_place && $student->birth_date ? ', ' : '').($student->birth_date?->translatedFormat('j F Y') ?? '')) ?: '-' }}</td><td class="label">Angkatan</td><td>{{ $student->batch_year ?? '-' }}</td></tr>
    </table>

    @if ($transcript['legacy_credits'] > 0)
        <p>Nilai awal sebelum sistem ini dipakai: {{ $transcript['legacy_credits'] }} SKS, total mutu {{ $num($transcript['legacy_quality_points'], 1) }}.</p>
    @endif

    @foreach ($transcript['groups'] as $rows)
        @php $term = $rows->first()['term']; @endphp
        <h3>{{ $term->name }} · {{ $term->semester }}</h3>
        <table class="grid">
            <thead>
                <tr><th style="width:12%">Kode</th><th>Mata kuliah</th><th style="width:7%">SKS</th><th style="width:9%">Nilai</th><th style="width:8%">Huruf</th><th style="width:9%">Mutu</th></tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td>{{ $row['subject']->code }}</td>
                        <td>{{ $row['subject']->name }}@if ($row['kind'] === \App\Enums\GradeKind::Remedial) (Remedial)@endif @if ($row['repeated']) (Mengulang)@endif</td>
                        <td class="c">{{ $row['credits'] }}</td>
                        <td class="r">{{ $num($row['total'], 1) }}</td>
                        <td class="c"><b>{{ $row['letter'] }}</b></td>
                        <td class="r">{{ $num($row['points'], 1) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach

    <table class="summary">
        <tr><td style="width:30%">SKS lulus</td><td><b>{{ $transcript['credits_passed'] }}</b></td></tr>
        <tr><td>SKS dihitung</td><td><b>{{ $transcript['credits_counted'] }}</b></td></tr>
        <tr><td>Indeks Prestasi Kumulatif (IPK)</td><td><b>{{ $transcript['ipk'] === null ? '-' : $num($transcript['ipk']) }}</b></td></tr>
    </table>

    <p class="foot">Mutu: A = 4, B = 3, C = 2, D = 1, E = 0. Hasil terbaik dipakai untuk mata kuliah yang diulang atau diremedial. Dicetak {{ now()->translatedFormat('j F Y, H.i') }} oleh {{ $printedBy }} · Aksaraloka Academic.</p>
</body>
</html>
