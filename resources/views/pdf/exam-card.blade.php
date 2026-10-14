<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Kartu {{ $type->label() }} {{ $student->nim }}</title>
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
        table.grid th { background: #f1f5f9; border: 1px solid #cbd5e1; padding: 4px; font-size: 8.5pt; text-align: left; }
        table.grid td { border: 1px solid #cbd5e1; padding: 5px 4px; }
        tr { page-break-inside: avoid; }
        .c { text-align: center; }
        .sign { margin-top: 28px; width: 100%; }
        .sign td { width: 50%; vertical-align: top; padding-top: 4px; }
        .foot { margin-top: 14px; font-size: 7.5pt; color: #64748b; }
    </style>
</head>
<body>
    <div class="kop">
        <h1>{{ $tenant->name }}</h1>
        <p>Biro Administrasi Akademik dan Kemahasiswaan</p>
    </div>

    <h2>Kartu Peserta {{ $type->fullLabel() }}</h2>

    <table class="meta">
        <tr><td class="label">Nama</td><td><b>{{ $student->name }}</b></td><td class="label">Semester</td><td>{{ $term->name }} · {{ $term->semester }}</td></tr>
        <tr><td class="label">NIM</td><td>{{ $student->nim }}</td><td class="label">Program studi</td><td>{{ $student->program?->label() ?? '-' }}</td></tr>
    </table>

    <table class="grid">
        <thead><tr><th class="c" style="width:5%">No</th><th>Mata kuliah</th><th>Hari, tanggal</th><th>Jam</th><th>Ruang</th></tr></thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td class="c">{{ $loop->iteration }}</td>
                    <td>{{ $row['assignment']->subject->code }} · {{ $row['assignment']->subject->name }}</td>
                    <td>{{ $row['exam']->date->translatedFormat('l, j F Y') }}</td>
                    <td>{{ $row['exam']->timeRange() }}</td>
                    <td>{{ $row['exam']->room?->name ?? '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="sign">
        <tr>
            <td>Pemegang kartu,<br><br><br><br>{{ $student->name }}</td>
            <td>Mengetahui,<br>Biro Akademik<br><br><br><br>(................................)</td>
        </tr>
    </table>

    <p class="foot">Kartu hanya memuat mata kuliah yang memenuhi syarat kehadiran minimal {{ $tenant->setting('min_attendance_percent') }}%. Bawa kartu ini dan identitas saat ujian. Dicetak oleh {{ $printedBy }} pada {{ now()->translatedFormat('j F Y H:i') }}.</p>
</body>
</html>
