<div class="mx-auto max-w-md space-y-6" wire:poll.5s="refreshToken">
    <x-page-header :title="'QR presensi · '.$session->studyGroup->name" subtitle="Kode berganti tiap 30 detik. Siswa memindai dari akunnya sendiri.">
        <x-slot:actions>
            <x-button :href="route('attendance.mark', $session)" wire:navigate variant="secondary" icon="arrow-left">Kembali</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card>
        <div x-data="qrCode" x-effect="draw($wire.payload)" class="mx-auto aspect-square w-full max-w-72 rounded-lg bg-white p-3 [&_svg]:h-full [&_svg]:w-full" role="img" aria-label="Kode QR presensi" wire:ignore></div>
        <p class="mt-3 text-center text-xs text-text-muted" role="status">Berganti dalam sekitar {{ $seconds }} detik</p>
        <p class="mt-3 text-center font-mono text-xs break-all text-text-muted">{{ $payload }}</p>
    </x-card>

    <x-card>
        <x-progress label="Sudah memindai" :value="$presentCount" :max="$total" empty="Belum ada siswa di rombel ini" />
    </x-card>
</div>
