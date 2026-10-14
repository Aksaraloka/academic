<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>SKL {{ $decision->student->nim }}</title>
    <style>
        @page { margin: 20mm 18mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10pt; color: #0f172a; line-height: 1.5; }
        .kop { border-bottom: 2px solid #c62828; padding-bottom: 6px; margin-bottom: 14px; }
        .kop h1 { margin: 0; font-size: 15pt; } .kop p { margin: 2px 0 0; color: #475569; }
        h2 { text-align: center; font-size: 13pt; margin: 14px 0 2px; text-transform: uppercase; text-decoration: underline; }
        .no { text-align: center; margin-bottom: 14px; color: #475569; }
        table.meta { width: 100%; border-collapse: collapse; margin: 8px 0 12px; }
        table.meta td { padding: 3px 0; vertical-align: top; } table.meta td.label { width: 30%; color: #475569; }
        .predicate { text-align: center; font-size: 13pt; font-weight: bold; margin: 14px 0; }
        .sign { margin-top: 34px; width: 100%; } .sign td { width: 50%; }
        .foot { margin-top: 20px; font-size: 7.5pt; color: #64748b; }
    </style>
</head>
<body>
    <div class="kop"><h1>{{ $tenant->name }}</h1><p>Biro Administrasi Akademik dan Kemahasiswaan</p></div>

    <h2>Surat Keterangan Lulus</h2>
    <p class="no">{{ $decision->yudisium->number ? 'Nomor: '.$decision->yudisium->number : 'Yudisium: '.$decision->yudisium->name }}</p>

    <p>Yang bertanda tangan di bawah ini menerangkan bahwa:</p>
    <table class="meta">
        <tr><td class="label">Nama</td><td><b>{{ $decision->student->name }}</b></td></tr>
        <tr><td class="label">NIM</td><td>{{ $decision->student->nim }}</td></tr>
        <tr><td class="label">Program studi</td><td>{{ $decision->student->program?->label() ?? '-' }}</td></tr>
        <tr><td class="label">Fakultas</td><td>{{ $decision->student->program?->faculty?->name ?? '-' }}</td></tr>
        <tr><td class="label">Total SKS lulus</td><td>{{ $decision->credits }} SKS</td></tr>
        <tr><td class="label">Indeks Prestasi Kumulatif</td><td>{{ number_format((float) $decision->gpa, 2, ',', '.') }}</td></tr>
    </table>

    <p>telah memenuhi seluruh persyaratan akademik dan dinyatakan <b>LULUS</b> pada rapat yudisium tanggal {{ $decision->yudisium->held_on->translatedFormat('j F Y') }} dengan predikat:</p>
    <p class="predicate">{{ mb_strtoupper($decision->predicate) }}</p>
    <p>Surat keterangan ini dibuat untuk dipergunakan sebagaimana mestinya sampai ijazah diterbitkan.</p>

    <table class="sign"><tr><td>&nbsp;</td><td>Dekan / Ketua Program Studi,<br><br><br><br>(................................)</td></tr></table>
    <p class="foot">Dicetak oleh {{ $printedBy }} pada {{ now()->translatedFormat('j F Y H:i') }}.</p>
</body>
</html>
