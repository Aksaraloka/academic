<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#c62828">
    <title>{{ $title ?? 'Masuk' }} · Aksaraloka Academic</title>
    @include('partials.theme-script')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full">
<main class="grid min-h-screen lg:grid-cols-2">
    <section class="relative flex flex-col justify-between overflow-hidden bg-brand px-6 py-8 text-brand-fg sm:px-10 lg:py-12">
        <span class="pointer-events-none absolute -right-16 -top-16 size-64 rounded-full bg-white/10" aria-hidden="true"></span>
        <span class="pointer-events-none absolute -bottom-20 -left-10 hidden size-72 rounded-full bg-white/10 lg:block" aria-hidden="true"></span>
        <div class="relative flex items-center gap-3">
            <span class="flex size-11 items-center justify-center rounded-lg bg-white/15"><x-icon name="academic-cap" class="size-6" /></span>
            <span class="text-lg font-bold">Aksaraloka <span class="font-medium opacity-80">Academic</span></span>
        </div>
        <div class="relative mt-10 hidden max-w-md lg:block">
            <h2 class="text-3xl font-bold leading-tight">Satu data induk, presensi selesai kurang dari semenit.</h2>
            <p class="mt-3 text-base opacity-90">Jadwal tanpa bentrok, presensi kelas lewat beberapa ketukan, dan orang tua langsung tahu kabar anaknya.</p>
        </div>
        <p class="relative mt-6 text-sm opacity-80 lg:mt-0">Sistem informasi akademik dan presensi untuk kampus dan kampus.</p>
    </section>
    <section class="flex items-center justify-center px-4 py-10 sm:px-6">
        <div class="w-full max-w-md">
            {{ $slot }}
        </div>
    </section>
</main>
</body>
</html>
