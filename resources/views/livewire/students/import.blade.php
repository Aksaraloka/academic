<div class="mx-auto max-w-2xl space-y-6">
    <x-page-header title="Impor siswa" subtitle="Unggah berkas CSV. Kamu bisa memeriksa hasilnya sebelum disimpan.">
        <x-slot:actions>
            <x-button :href="route('students.index')" wire:navigate variant="secondary" icon="arrow-left">Kembali</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card title="1. Siapkan berkas">
        <p class="text-sm text-text-muted">Kolom yang dipakai: <span class="font-medium text-text">{{ implode(', ', \App\Services\StudentImporter::COLUMNS) }}</span>. Kolom <em>nis</em> dan <em>nama</em> wajib. Isi <em>rombel</em> (mis. 7A) agar siswa langsung ditempatkan.</p>
        <div class="mt-3">
            <x-button :href="route('students.template')" variant="secondary" size="sm" icon="arrow-down-tray">Unduh templat</x-button>
        </div>
    </x-card>

    <x-card title="2. Unggah dan periksa">
        <x-field label="Berkas CSV" name="file" hint="Maksimal 2 MB.">
            <input id="file" type="file" wire:model="file" accept=".csv,text/csv,text/plain" class="input">
        </x-field>
        <p wire:loading wire:target="file" class="mt-2 text-sm text-text-muted" role="status">Membaca berkas…</p>

        @if ($preview)
            <div class="mt-4 space-y-3" role="status">
                <div class="flex flex-wrap gap-2">
                    <x-badge variant="success" icon="check-circle">{{ count($preview['valid']) }} baris siap diimpor</x-badge>
                    @if ($preview['errors'])
                        <x-badge variant="danger" icon="x-circle">{{ count($preview['errors']) }} baris gagal</x-badge>
                    @endif
                </div>

                @if ($preview['errors'])
                    <ul class="divide-y divide-border rounded-lg border border-border text-sm">
                        @foreach ($preview['errors'] as $error)
                            <li class="flex gap-3 px-3 py-2"><span class="shrink-0 font-semibold tabular-nums">Baris {{ $error['row'] }}</span><span class="text-danger">{{ $error['message'] }}</span></li>
                        @endforeach
                    </ul>
                @endif

                @if ($preview['valid'])
                    <x-button wire:click="confirm" wire:loading.attr="disabled" wire:target="confirm" icon="check">Impor {{ count($preview['valid']) }} siswa</x-button>
                @endif
            </div>
        @endif

        @if ($imported)
            <p class="mt-4 text-sm font-medium text-success" role="status">{{ $imported }} siswa berhasil diimpor.</p>
        @endif
    </x-card>
</div>
