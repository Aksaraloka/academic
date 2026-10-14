<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Berita Acara {{ $minute->number }}</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #e5e7eb; color: #000; font-family: 'Times New Roman', Times, serif; font-size: 12pt; line-height: 1.5; }
        .toolbar { position: sticky; top: 0; display: flex; gap: .5rem; justify-content: flex-end; padding: .75rem 1rem; background: #fff; border-bottom: 1px solid #d1d5db; font-family: system-ui, sans-serif; }
        .toolbar button, .toolbar a { min-height: 44px; padding: 0 1rem; border: 1px solid #c62828; border-radius: .5rem; background: #c62828; color: #fff; font: inherit; font-size: 14px; display: inline-flex; align-items: center; text-decoration: none; cursor: pointer; }
        .toolbar a { background: #fff; color: #c62828; }
        .page { width: 210mm; max-width: 100%; min-height: 297mm; margin: 1rem auto; padding: 20mm 22mm; background: #fff; }
        .kop { text-align: center; border-bottom: 3px double #000; padding-bottom: .5rem; margin-bottom: 1.25rem; }
        .kop h1 { margin: 0; font-size: 16pt; letter-spacing: .03em; text-transform: uppercase; }
        .kop p { margin: 0; font-size: 10pt; }
        h2 { text-align: center; margin: 0; font-size: 13pt; text-decoration: underline; text-transform: uppercase; }
        .nomor { text-align: center; margin: 0 0 1rem; }
        table.data { width: 100%; border-collapse: collapse; margin: .75rem 0; font-size: 10.5pt; }
        table.data th, table.data td { border: 1px solid #000; padding: 4px 6px; vertical-align: top; text-align: left; }
        table.data th { background: #f3f4f6; text-align: center; }
        .sign { display: flex; justify-content: space-between; gap: 2rem; margin-top: 2rem; page-break-inside: avoid; }
        .sign div { width: 48%; text-align: center; }
        .sign .space { height: 70px; }
        @media (max-width: 640px) { .page { padding: 1rem; min-height: 0; } .sign { flex-direction: column; } .sign div { width: 100%; } table.data { display: block; overflow-x: auto; } }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .page { margin: 0; width: auto; min-height: 0; padding: 0; }
            @page { size: A4; margin: 20mm 22mm; }
        }
    </style>
</head>
<body>
<div class="toolbar">
    <a href="{{ route('graduation.minutes', $period) }}">Kembali</a>
    <button type="button" onclick="window.print()">Cetak / simpan PDF</button>
</div>

<main class="page">
    <header class="kop">
        <h1>{{ $tenant->name }}</h1>
        <p>Panitia Wisuda — {{ $period->name }}</p>
    </header>

    <h2>Berita Acara Pendaftaran Wisuda</h2>
    <p class="nomor">Nomor: {{ $minute->number }}</p>

    <p>
        Pada hari ini, {{ $minute->issued_on->translatedFormat('l') }}, tanggal {{ $minute->issued_on->translatedFormat('j F Y') }},
        panitia telah selesai memeriksa dan memverifikasi berkas pendaftaran wisuda <strong>{{ $period->name }}</strong>
        yang dibuka pada {{ $period->registration_opens_on->translatedFormat('j F Y') }} sampai dengan {{ $period->registration_closes_on->translatedFormat('j F Y') }}.
        Upacara wisuda direncanakan pada {{ $period->ceremony_on->translatedFormat('l, j F Y') }}@if ($period->venue) bertempat di {{ $period->venue }}@endif.
    </p>

    <p>Berdasarkan hasil verifikasi, <strong>{{ $participants->count() }} (peserta)</strong> dinyatakan memenuhi persyaratan dan terdaftar sebagai peserta wisuda, dengan rincian sebagai berikut:</p>

    <table class="data">
        <thead>
            <tr><th style="width:5%">No</th><th>Nama</th><th style="width:14%">NIM</th><th>Program studi</th><th style="width:8%">IPK</th><th>Judul tugas akhir</th></tr>
        </thead>
        <tbody>
            @foreach ($participants as $participant)
                <tr>
                    <td style="text-align:center">{{ $loop->iteration }}</td>
                    <td>{{ $participant['name'] }}</td>
                    <td>{{ $participant['nim'] }}</td>
                    <td>{{ $participant['program'] }}</td>
                    <td style="text-align:center">{{ $participant['gpa'] }}</td>
                    <td>{{ $participant['thesis_title'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($minute->notes)
        <p><strong>Catatan:</strong> {{ $minute->notes }}</p>
    @endif

    <p>Demikian berita acara ini dibuat dengan sebenarnya untuk dipergunakan sebagaimana mestinya.</p>

    <div class="sign">
        <div>
            <p>Mengetahui,<br>{{ $minute->approver_title }}</p>
            <div class="space"></div>
            <p><strong><u>{{ $minute->approver_name }}</u></strong></p>
        </div>
        <div>
            <p>{{ $minute->issued_on->translatedFormat('j F Y') }}<br>{{ $minute->chair_title }}</p>
            <div class="space"></div>
            <p><strong><u>{{ $minute->chair_name }}</u></strong></p>
        </div>
    </div>
</main>
</body>
</html>
