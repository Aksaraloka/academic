@php
    $type = $letter->type;
    $student = $data['student'];
    $gpa = $data['gpa'] === null ? '-' : number_format((float) $data['gpa'], 2, ',', '.');
    $details = $letter->details ?? [];
    $signatory = $tenant->setting('letter_signatory_name');
    $signatoryTitle = $tenant->setting('letter_signatory_title');
    $date = fn (?string $value): string => $value ? \Illuminate\Support\Carbon::parse($value)->translatedFormat('j F Y') : '-';
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $type->label() }} {{ $letter->number }}</title>
    <style>
        @page { margin: 20mm 18mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10pt; color: #0f172a; line-height: 1.55; }
        .kop { border-bottom: 2px solid #c62828; padding-bottom: 6px; margin-bottom: 14px; }
        .kop h1 { margin: 0; font-size: 15pt; } .kop p { margin: 2px 0 0; color: #475569; }
        h2 { text-align: center; font-size: 13pt; margin: 14px 0 2px; text-transform: uppercase; text-decoration: underline; }
        .no { text-align: center; margin-bottom: 14px; color: #475569; }
        table.meta { width: 100%; border-collapse: collapse; margin: 8px 0 12px; }
        table.meta td { padding: 3px 0; vertical-align: top; } table.meta td.label { width: 30%; color: #475569; }
        table.grid { width: 100%; border-collapse: collapse; margin: 6px 0; font-size: 8.5pt; }
        table.grid th { background: #f1f5f9; border: 1px solid #cbd5e1; padding: 4px; }
        table.grid td { border: 1px solid #cbd5e1; padding: 3px 4px; }
        tr { page-break-inside: avoid; }
        .c { text-align: center; } .r { text-align: right; }
        .sign { margin-top: 26px; width: 100%; } .sign td { vertical-align: top; }
        .qr img { width: 74px; height: 74px; } .qr p { margin: 2px 0 0; font-size: 7pt; color: #64748b; }
        .foot { margin-top: 16px; font-size: 7.5pt; color: #64748b; }
        .mark { text-align: center; font-weight: bold; color: #c62828; margin: 4px 0 8px; }
    </style>
</head>
<body>
    <div class="kop"><h1>{{ $tenant->name }}</h1><p>Biro Administrasi Akademik dan Kemahasiswaan</p></div>

    <h2>{{ $type->label() }}</h2>
    <p class="no">Nomor: {{ $letter->number }}</p>

    @if ($type === \App\Enums\LetterType::ResearchPermit)
        <p>Yth. {{ $details['addressee'] ?? 'Pimpinan' }}<br>{{ $details['institution'] ?? '' }}<br>di tempat</p>
        <p>Dengan hormat, kami menerangkan bahwa mahasiswa berikut:</p>
    @else
        <p>Yang bertanda tangan di bawah ini menerangkan bahwa:</p>
    @endif

    <table class="meta">
        <tr><td class="label">Nama</td><td><b>{{ $student['name'] }}</b></td></tr>
        <tr><td class="label">NIM</td><td>{{ $student['nim'] }}</td></tr>
        @if ($student['birth'])<tr><td class="label">Tempat, tanggal lahir</td><td>{{ $student['birth'] }}</td></tr>@endif
        <tr><td class="label">Program studi</td><td>{{ $student['program'] ?? '-' }}</td></tr>
        <tr><td class="label">Fakultas</td><td>{{ $student['faculty'] ?? '-' }}</td></tr>
        @if ($type !== \App\Enums\LetterType::ResearchPermit)<tr><td class="label">Angkatan</td><td>{{ $student['batch_year'] ?? '-' }}</td></tr>@endif
    </table>

    @switch($type)
        @case(\App\Enums\LetterType::ActiveStudent)
            <p>adalah benar mahasiswa <b>aktif</b> pada {{ $tenant->name }} untuk semester {{ $data['term'] ?? '-' }} (semester ke-{{ $data['semester'] }}).</p>
            <p>Surat keterangan ini dibuat untuk keperluan <b>{{ $letter->purpose }}</b> dan dipergunakan sebagaimana mestinya.</p>
            @break

        @case(\App\Enums\LetterType::ProvisionalTranscript)
            <p class="mark">TRANSKRIP SEMENTARA — bukan dokumen kelulusan</p>
            <table class="grid">
                <thead><tr><th style="width:15%">Semester</th><th style="width:11%">Kode</th><th>Mata kuliah</th><th style="width:7%">SKS</th><th style="width:8%">Huruf</th><th style="width:8%">Mutu</th></tr></thead>
                <tbody>
                    @foreach ($data['rows'] ?? [] as $row)
                        <tr>
                            <td>{{ $row['term'] }}</td><td>{{ $row['code'] }}</td><td>{{ $row['name'] }}</td>
                            <td class="c">{{ $row['credits'] }}</td><td class="c"><b>{{ $row['letter'] }}</b></td><td class="r">{{ number_format((float) $row['points'], 1, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if (($data['legacy_credits'] ?? 0) > 0)<p>Termasuk nilai awal sebelum sistem ini dipakai: {{ $data['legacy_credits'] }} SKS.</p>@endif
            <p>SKS lulus: <b>{{ $data['credits'] }}</b> · Indeks Prestasi Kumulatif: <b>{{ $gpa }}</b>. Surat ini dibuat untuk keperluan {{ $letter->purpose }}.</p>
            @break

        @case(\App\Enums\LetterType::Recommendation)
            <p>adalah mahasiswa kami dengan capaian akademik {{ $data['credits'] }} SKS lulus dan Indeks Prestasi Kumulatif {{ $gpa }}.
               Berdasarkan penilaian program studi{{ ! empty($data['recommender']) ? ' dan '.$data['recommender'] : '' }}, yang bersangkutan layak <b>direkomendasikan</b> untuk
               <b>{{ $letter->purpose }}</b>@if (! empty($details['addressee'])) pada {{ $details['addressee'] }}@endif.</p>
            <p>Demikian surat rekomendasi ini dibuat agar dapat dipergunakan sebagaimana mestinya.</p>
            @break

        @case(\App\Enums\LetterType::ResearchPermit)
            <p>akan melaksanakan penelitian dengan topik <b>“{{ $letter->purpose }}”</b>
               di {{ $details['institution'] ?? 'instansi Bapak/Ibu' }}@if (! empty($details['period_start'])) pada periode {{ $date($details['period_start']) }} s.d. {{ $date($details['period_end'] ?? null) }}@endif.</p>
            <p>Sehubungan dengan hal tersebut, kami mohon Bapak/Ibu berkenan memberikan izin dan bantuan yang diperlukan. Atas perhatian dan kerja samanya, kami ucapkan terima kasih.</p>
            @break
    @endswitch

    <table class="sign">
        <tr>
            <td class="qr" style="width:50%">
                <img src="{{ $qr }}" alt="QR verifikasi">
                <p>Pindai untuk memverifikasi keaslian surat.<br>Kode: {{ $letter->verification_code }}</p>
            </td>
            <td>
                {{ $letter->issued_at->translatedFormat('j F Y') }}<br>{{ $signatoryTitle }},<br><br><br><br>
                @if ($signatory)<b>{{ $signatory }}</b>@else (................................) @endif
            </td>
        </tr>
    </table>
    <p class="foot">Surat ini diterbitkan melalui Aksaraloka Academic dan sah tanpa cap basah selama kode verifikasi pada QR cocok dengan data kampus.</p>
</body>
</html>
