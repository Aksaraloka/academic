<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="#c62828">
    <title>Verifikasi surat · Aksaraloka Academic</title>
    @include('partials.theme-script')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-bg">
<main class="mx-auto flex min-h-screen max-w-lg flex-col justify-center px-4 py-10">
    <div class="mb-6 flex items-center gap-3">
        <span class="flex size-11 items-center justify-center rounded-lg bg-primary text-primary-fg"><x-icon name="academic-cap" class="size-6" /></span>
        <span class="text-lg font-bold">Aksaraloka <span class="font-medium text-text-muted">Academic</span></span>
    </div>

    @if ($letter)
        <x-card>
            <div class="flex items-center gap-3">
                <span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-success/15 text-success"><x-icon name="check-badge" class="size-6" /></span>
                <div>
                    <h1 class="text-xl font-bold">Surat sah</h1>
                    <p class="text-sm text-text-muted">Surat ini tercatat diterbitkan oleh {{ $tenant->name }}.</p>
                </div>
            </div>
            <x-info-list class="mt-5" :items="[
                'Jenis surat' => $letter->type->label(),
                'Nomor surat' => $letter->number,
                'Tanggal terbit' => $letter->issued_at->translatedFormat('j F Y'),
                'Nama mahasiswa' => $letter->snapshot['student']['name'],
                'NIM' => $maskedNim,
                'Program studi' => $letter->snapshot['student']['program'],
            ]" />
            <p class="mt-5 text-xs text-text-muted">NIM ditampilkan sebagian demi privasi. Cocokkan nomor dan nama dengan surat yang kamu pegang; bila berbeda, anggap surat tidak sah.</p>
        </x-card>
    @else
        <x-card>
            <div class="flex items-center gap-3">
                <span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-danger/15 text-danger"><x-icon name="exclamation-triangle" class="size-6" /></span>
                <div>
                    <h1 class="text-xl font-bold">Surat tidak ditemukan</h1>
                    <p class="text-sm text-text-muted">Kode verifikasi ini tidak cocok dengan surat yang pernah diterbitkan. Surat yang kamu pegang mungkin palsu atau kodenya salah ketik.</p>
                </div>
            </div>
        </x-card>
    @endif
</main>
</body>
</html>
