<div class="space-y-6">
    <x-page-header title="Berita acara pendaftaran wisuda" :subtitle="$period->name">
        <x-slot:actions>
            <x-button :href="route('wisuda.overview', ['periode' => $period->id])" wire:navigate variant="secondary" icon="arrow-left">Pendaftaran wisuda</x-button>
            @if ($finalized)
                <x-button :href="route('wisuda.minutes.print', $period)" target="_blank" rel="noopener" icon="document-text">Cetak</x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($finalized)
        <div class="flex flex-wrap items-center gap-2" role="status">
            <x-badge variant="success" icon="check-circle">Diterbitkan {{ $minute->finalized_at->translatedFormat('j F Y, H.i') }}</x-badge>
            <span class="text-sm tabular-nums text-text-muted">{{ $minute->number }}</span>
        </div>
    @elseif ($pending > 0)
        <p class="flex items-center gap-2 rounded-lg border border-warning bg-warning/15 p-3 text-sm text-warning" role="status">
            <x-icon name="exclamation-triangle" class="size-4" /> Masih ada {{ $pending }} pendaftaran menunggu verifikasi. Selesaikan dulu sebelum menerbitkan berita acara.
        </p>
    @endif

    <form wire:submit="finalize">
        <x-card title="Data berita acara">
            <fieldset @disabled(! $canEdit) class="grid gap-4 sm:grid-cols-2">
                <x-field label="Nomor" name="number" required>
                    <input id="number" type="text" wire:model="number" class="input" @error('number') aria-invalid="true" @enderror>
                </x-field>
                <x-field label="Tanggal terbit" name="issued_on" required>
                    <input id="issued_on" type="date" wire:model="issued_on" class="input">
                </x-field>
                <x-field label="Nama ketua panitia" name="chair_name" required>
                    <input id="chair_name" type="text" wire:model="chair_name" class="input" @error('chair_name') aria-invalid="true" @enderror>
                </x-field>
                <x-field label="Jabatan" name="chair_title" required>
                    <input id="chair_title" type="text" wire:model="chair_title" class="input">
                </x-field>
                <x-field label="Nama pejabat pengesah" name="approver_name" required>
                    <input id="approver_name" type="text" wire:model="approver_name" class="input" @error('approver_name') aria-invalid="true" @enderror>
                </x-field>
                <x-field label="Jabatan" name="approver_title" required>
                    <input id="approver_title" type="text" wire:model="approver_title" class="input">
                </x-field>
                <div class="sm:col-span-2">
                    <x-field label="Catatan" name="notes" hint="Opsional, tampil di bagian akhir dokumen.">
                        <textarea id="notes" wire:model="notes" rows="2" class="input"></textarea>
                    </x-field>
                </div>
            </fieldset>

            @if ($canEdit)
                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <x-button type="submit" icon="check" wire:loading.attr="disabled" wire:target="finalize" :disabled="$participants->isEmpty()">Terbitkan berita acara</x-button>
                    <p class="text-sm text-text-muted">Setelah terbit, daftar peserta dibekukan dan keputusan verifikasi dikunci.</p>
                </div>
            @endif
        </x-card>
    </form>

    <x-card :title="'Peserta ('.$participants->count().')'" :padding="false">
        @if ($participants->isEmpty())
            <x-empty-state icon="academic-cap" title="Belum ada peserta yang disetujui" description="Setujui pendaftaran lebih dulu agar masuk berita acara." />
        @else
            <table class="table-responsive">
                <thead>
                    <tr><th scope="col">No</th><th scope="col">Nama</th><th scope="col">NIM</th><th scope="col">Program studi</th><th scope="col">IPK</th></tr>
                </thead>
                <tbody>
                    @foreach ($participants as $participant)
                        <tr wire:key="part-{{ $loop->index }}">
                            <td data-label="No" class="tabular-nums">{{ $loop->iteration }}</td>
                            <td data-label="Nama" class="font-medium">{{ $participant['name'] }}</td>
                            <td data-label="NIM" class="tabular-nums text-text-muted">{{ $participant['nim'] }}</td>
                            <td data-label="Program studi" class="text-text-muted">{{ $participant['program'] }}</td>
                            <td data-label="IPK" class="tabular-nums">{{ $participant['gpa'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-card>
</div>
