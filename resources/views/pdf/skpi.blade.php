<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>SKPI {{ $student->nim }}</title>
    <style>
        @page { margin: 18mm 16mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #0f172a; line-height: 1.45; }
        .kop { border-bottom: 2px solid #c62828; padding-bottom: 6px; margin-bottom: 10px; }
        .kop h1 { margin: 0; font-size: 14pt; } .kop p { margin: 2px 0 0; color: #475569; }
        h2 { text-align: center; font-size: 12pt; margin: 8px 0 2px; text-transform: uppercase; }
        .sub { text-align: center; color: #475569; margin-bottom: 10px; }
        h3 { font-size: 10pt; margin: 12px 0 4px; }
        table.meta { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        table.meta td { padding: 2px 0; vertical-align: top; } table.meta td.label { width: 22%; color: #475569; }
        table.grid { width: 100%; border-collapse: collapse; }
        table.grid th { background: #f1f5f9; border: 1px solid #cbd5e1; padding: 4px; font-size: 8pt; }
        table.grid td { border: 1px solid #cbd5e1; padding: 3px 4px; vertical-align: top; }
        tr { page-break-inside: avoid; }
        .c { text-align: center; } .muted { color: #64748b; font-size: 8pt; }
        .total { margin-top: 12px; font-weight: bold; }
        .foot { margin-top: 14px; font-size: 7.5pt; color: #64748b; }
    </style>
</head>
<body>
    <div class="kop"><h1>{{ $tenant->name }}</h1><p>Biro Administrasi Akademik dan Kemahasiswaan</p></div>

    <h2>Surat Keterangan Pendamping Ijazah (SKPI)</h2>
    <p class="sub">Capaian kegiatan kemahasiswaan yang telah diverifikasi</p>

    <table class="meta">
        <tr><td class="label">Nama</td><td><b>{{ $student->name }}</b></td><td class="label">Program studi</td><td>{{ $student->program?->label() ?? '-' }}</td></tr>
        <tr><td class="label">NIM</td><td>{{ $student->nim }}</td><td class="label">Fakultas</td><td>{{ $student->program?->faculty?->name ?? '-' }}</td></tr>
    </table>

    @forelse ($skpi['groups'] as $category => $activities)
        <h3>{{ \App\Enums\ActivityCategory::from($category)->label() }}</h3>
        <table class="grid">
            <thead><tr><th style="width:5%">No</th><th>Kegiatan</th><th style="width:22%">Penyelenggara</th><th style="width:15%">Periode</th><th style="width:13%">Tingkat</th><th style="width:7%">Poin</th></tr></thead>
            <tbody>
                @foreach ($activities as $activity)
                    <tr>
                        <td class="c">{{ $loop->iteration }}</td>
                        <td><b>{{ $activity->title }}</b>@if ($activity->role_or_result)<br><span class="muted">{{ $activity->role_or_result }}</span>@endif</td>
                        <td>{{ $activity->organizer }}</td>
                        <td>{{ $activity->periodLabel() }}</td>
                        <td>{{ $activity->level->label() }}</td>
                        <td class="c">{{ $activity->points }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @empty
        <p>Belum ada kegiatan yang terverifikasi.</p>
    @endforelse

    <p class="total">Total poin SKPI: {{ $skpi['total_points'] }} dari {{ $skpi['count'] }} kegiatan terverifikasi.</p>
    <p class="foot">Hanya kegiatan yang sudah diverifikasi staf akademik yang tercantum. Dicetak {{ now()->translatedFormat('j F Y, H.i') }} oleh {{ $printedBy }} · Aksaraloka Academic.</p>
</body>
</html>
