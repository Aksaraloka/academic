<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>BAP {{ $assignment->subject->code }}</title>
    <style>
        @page { margin: 14mm 12mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8.5pt; color: #0f172a; }
        .kop { border-bottom: 2px solid #c62828; padding-bottom: 6px; margin-bottom: 8px; }
        .kop h1 { margin: 0; font-size: 13pt; }
        .kop p { margin: 2px 0 0; color: #475569; }
        h2 { text-align: center; font-size: 11.5pt; margin: 6px 0 8px; text-transform: uppercase; }
        table.meta { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        table.meta td { padding: 2px 0; }
        table.meta td.label { width: 14%; color: #475569; }
        table.grid { width: 100%; border-collapse: collapse; }
        table.grid th { background: #f1f5f9; border: 1px solid #cbd5e1; padding: 4px; font-size: 8pt; text-align: left; }
        table.grid td { border: 1px solid #cbd5e1; padding: 4px; vertical-align: top; }
        tr { page-break-inside: avoid; }
        .c { text-align: center; }
        .sign { margin-top: 18px; width: 100%; }
        .sign td { width: 50%; padding-top: 4px; }
        .foot { margin-top: 8px; font-size: 7.5pt; color: #64748b; }
    </style>
</head>
<body>
    <div class="kop">
        <h1>{{ $tenant->name }}</h1>
        <p>Biro Administrasi Akademik dan Kemahasiswaan</p>
    </div>

    <h2>Berita Acara Perkuliahan (BAP)</h2>

    <table class="meta">
        <tr><td class="label">Mata kuliah</td><td><b>{{ $assignment->subject->code }} · {{ $assignment->subject->name }}</b> ({{ $assignment->subject->credits }} SKS)</td><td class="label">Semester</td><td>{{ $assignment->academicTerm->name }} · {{ $assignment->academicTerm->semester }}</td></tr>
        <tr><td class="label">Dosen</td><td>{{ $assignment->teacher->name }}</td><td class="label">Kelas</td><td>{{ $assignment->classLabel() }}</td></tr>
    </table>

    <table class="grid">
        <thead><tr><th class="c" style="width:6%">Pert.</th><th style="width:14%">Tanggal</th><th>Pokok bahasan dan materi</th><th style="width:16%">Catatan</th><th class="c" style="width:9%">Hadir</th></tr></thead>
        <tbody>
            @foreach ($meetings as $meeting)
                <tr>
                    <td class="c">{{ $meeting['number'] }}</td>
                    <td>{{ $meeting['session']->date->translatedFormat('j M Y') }}</td>
                    <td>@if ($meeting['journal'])<b>{{ $meeting['journal']->topic }}</b>@if ($meeting['journal']->material)<br>{{ $meeting['journal']->material }}@endif @else <i>Belum diisi</i> @endif</td>
                    <td>{{ $meeting['journal']?->notes }}</td>
                    <td class="c">{{ $meeting['present'] }}/{{ $meeting['total'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="sign">
        <tr>
            <td>Dosen pengampu,<br><br><br>{{ $assignment->teacher->name }}</td>
            <td>Mengetahui, Ketua Program Studi<br><br><br>(................................)</td>
        </tr>
    </table>

    <p class="foot">Dicetak oleh {{ $printedBy }} pada {{ now()->translatedFormat('j F Y H:i') }}.</p>
</body>
</html>
