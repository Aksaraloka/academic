@php
    $num = fn (float $value, int $decimals = 2): string => number_format($value, $decimals, ',', '.');
    $term = $khs['term'];
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>KHS {{ $student->nim }} · {{ $term->name }} {{ $term->semester }}</title>
    <style>
        @page { margin: 18mm 14mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #0f172a; }
        .kop { border-bottom: 2px solid #c62828; padding-bottom: 6px; margin-bottom: 10px; }
        .kop h1 { margin: 0; font-size: 14pt; }
        .kop p { margin: 2px 0 0; color: #475569; }
        h2 { text-align: center; font-size: 12pt; margin: 8px 0 10px; text-transform: uppercase; }
        table.meta { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        table.meta td { padding: 2px 0; }
        table.meta td.label { width: 22%; color: #475569; }
        table.grid { width: 100%; border-collapse: collapse; }
        table.grid th { background: #f1f5f9; border: 1px solid #cbd5e1; padding: 5px 4px; font-size: 8.5pt; }
        table.grid td { border: 1px solid #cbd5e1; padding: 4px; }
        .r { text-align: right; } .c { text-align: center; }
        tr.total td { font-weight: bold; background: #f8fafc; }
        .summary { margin-top: 10px; width: 100%; border-collapse: collapse; }
        .summary td { padding: 3px 0; }
        .foot { margin-top: 14px; font-size: 7.5pt; color: #64748b; }
    </style>
</head>
<body>
    <div class="kop">
        <h1>{{ $tenant->name }}</h1>
        <p>Biro Administrasi Akademik dan Kemahasiswaan</p>
    </div>

    <h2>Kartu Hasil Studi</h2>

    <table class="meta">
        <tr><td class="label">Nama</td><td><b>{{ $student->name }}</b></td><td class="label">Semester</td><td><b>{{ $term->name }} · {{ $term->semester }}</b></td></tr>
        <tr><td class="label">NIM</td><td>{{ $student->nim }}</td><td class="label">Program studi</td><td>{{ $student->program?->label() ?? '-' }}</td></tr>
        <tr><td class="label">Angkatan</td><td>{{ $student->batch_year ?? '-' }}</td><td class="label">Fakultas</td><td>{{ $student->program?->faculty?->name ?? '-' }}</td></tr>
    </table>

    <table class="grid">
        <thead>
            <tr><th style="width:8%">No</th><th style="width:12%">Kode</th><th>Mata kuliah</th><th style="width:7%">SKS</th><th style="width:9%">Nilai</th><th style="width:8%">Huruf</th><th style="width:8%">Mutu</th><th style="width:11%">SKS × Mutu</th></tr>
        </thead>
        <tbody>
            @foreach ($khs['rows'] as $row)
                <tr>
                    <td class="c">{{ $loop->iteration }}</td>
                    <td>{{ $row['subject']->code }}</td>
                    <td>{{ $row['subject']->name }}@if ($row['kind'] === \App\Enums\GradeKind::Remedial) (Remedial)@endif</td>
                    <td class="c">{{ $row['credits'] }}</td>
                    <td class="r">{{ $num($row['total'], 1) }}</td>
                    <td class="c"><b>{{ $row['letter'] }}</b></td>
                    <td class="r">{{ $num($row['points'], 1) }}</td>
                    <td class="r">{{ $num($row['mutu'], 1) }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="3">Jumlah</td>
                <td class="c">{{ $khs['credits'] }}</td>
                <td colspan="3" class="r">IPS</td>
                <td class="r">{{ $khs['ips'] === null ? '-' : $num($khs['ips']) }}</td>
            </tr>
        </tbody>
    </table>

    <table class="summary">
        <tr><td style="width:30%">Indeks Prestasi Semester (IPS)</td><td><b>{{ $khs['ips'] === null ? '-' : $num($khs['ips']) }}</b></td></tr>
        <tr><td>Indeks Prestasi Kumulatif (IPK)</td><td><b>{{ $khs['cumulative']['ipk'] === null ? '-' : $num($khs['cumulative']['ipk']) }}</b></td></tr>
        <tr><td>SKS lulus kumulatif</td><td><b>{{ $khs['cumulative']['credits_passed'] }}</b></td></tr>
    </table>

    <p class="foot">Mutu: A = 4, B = 3, C = 2, D = 1, E = 0. Hasil terbaik dipakai untuk mata kuliah yang diulang atau diremedial. Dicetak {{ now()->translatedFormat('j F Y, H.i') }} oleh {{ $printedBy }} · Aksaraloka Academic.</p>
</body>
</html>
