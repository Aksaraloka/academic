<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $yudisium->name }}</title>
    <style>
        @page { margin: 14mm 12mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8.5pt; color: #0f172a; }
        .kop { border-bottom: 2px solid #c62828; padding-bottom: 6px; margin-bottom: 8px; }
        .kop h1 { margin: 0; font-size: 13pt; }
        .kop p { margin: 2px 0 0; color: #475569; }
        h2 { text-align: center; font-size: 11.5pt; margin: 6px 0 4px; text-transform: uppercase; }
        .sub { text-align: center; margin-bottom: 8px; color: #475569; }
        table.grid { width: 100%; border-collapse: collapse; }
        table.grid th { background: #f1f5f9; border: 1px solid #cbd5e1; padding: 4px; font-size: 8pt; text-align: left; }
        table.grid td { border: 1px solid #cbd5e1; padding: 4px; }
        tr { page-break-inside: avoid; }
        .c { text-align: center; } .r { text-align: right; }
        .sign { margin-top: 18px; width: 100%; } .sign td { width: 50%; padding-top: 4px; }
        .foot { margin-top: 8px; font-size: 7.5pt; color: #64748b; }
    </style>
</head>
<body>
    <div class="kop"><h1>{{ $tenant->name }}</h1><p>Biro Administrasi Akademik dan Kemahasiswaan</p></div>
    <h2>Keputusan Yudisium</h2>
    <p class="sub">{{ $yudisium->name }} · {{ $yudisium->held_on->translatedFormat('j F Y') }}{{ $yudisium->number ? ' · No. '.$yudisium->number : '' }}</p>

    <table class="grid">
        <thead><tr><th class="c" style="width:4%">No</th><th>Nama</th><th>NIM</th><th>Program studi</th><th class="c">SKS</th><th class="c">IPK</th><th>Predikat</th></tr></thead>
        <tbody>
            @foreach ($decisions as $decision)
                <tr>
                    <td class="c">{{ $loop->iteration }}</td><td>{{ $decision->student->name }}</td><td>{{ $decision->student->nim }}</td>
                    <td>{{ $decision->student->program?->label() }}</td><td class="c">{{ $decision->credits }}</td>
                    <td class="c">{{ number_format((float) $decision->gpa, 2, ',', '.') }}</td><td>{{ $decision->predicate }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="sign"><tr><td>&nbsp;</td><td>Ditetapkan di rapat yudisium,<br><br><br><br>(................................)</td></tr></table>
    <p class="foot">Dicetak oleh {{ $printedBy }} pada {{ now()->translatedFormat('j F Y H:i') }}.</p>
</body>
</html>
