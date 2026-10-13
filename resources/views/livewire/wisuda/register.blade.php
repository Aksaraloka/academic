<div class="mx-auto max-w-2xl space-y-6">
    <x-page-header :title="'Daftar wisuda'" :subtitle="$period->name">
        <x-slot:actions>
            <x-button :href="route('wisuda.overview', ['periode' => $period->id])" wire:navigate variant="secondary" icon="arrow-left">Kembali</x-button>
        </x-slot:actions>
    </x-page-header>

    @error('period')
        <p class="rounded-lg border border-danger bg-danger/15 p-3 text-sm text-danger" role="alert">{{ $message }}</p>
    @enderror

    @if ($locked)
        <x-card>
            <x-badge :variant="$existing->status->variant()" :icon="$existing->status->icon()">{{ $existing->status->label() }}</x-badge>
            <p class="mt-3 text-sm text-text-muted">Kamu sudah mendaftar di periode ini. Data tidak bisa diubah selama proses verifikasi.</p>
        </x-card>
    @else
        @if ($existing?->rejection_reason)
            <p class="rounded-lg border border-danger bg-danger/15 p-3 text-sm text-danger" role="alert">Pendaftaran sebelumnya ditolak: {{ $existing->rejection_reason }}. Perbaiki data lalu ajukan ulang.</p>
        @endif

        <form wire:submit="save" class="space-y-4">
            <x-card title="Data akademik">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <x-field label="Judul skripsi / tugas akhir" name="thesis_title" required>
                            <textarea id="thesis_title" wire:model="thesis_title" rows="3" class="input" @error('thesis_title') aria-invalid="true" @enderror></textarea>
                        </x-field>
                    </div>
                    <x-field label="IPK" name="gpa" required :hint="'Minimal '.$minGpa.'.'">
                        <input id="gpa" type="number" step="0.01" min="0" max="4" wire:model="gpa" class="input" inputmode="decimal" @error('gpa') aria-invalid="true" @enderror>
                    </x-field>
                    <x-field label="SKS lulus" name="credits_earned" required :hint="'Minimal '.$minCredits.' SKS.'">
                        <input id="credits_earned" type="number" min="0" wire:model="credits_earned" class="input" inputmode="numeric" @error('credits_earned') aria-invalid="true" @enderror>
                    </x-field>
                    <x-field label="Tanggal sidang akhir" name="defense_date" required>
                        <input id="defense_date" type="date" wire:model="defense_date" class="input" @error('defense_date') aria-invalid="true" @enderror>
                    </x-field>
                    <x-field label="Ukuran toga" name="gown_size">
                        <select id="gown_size" wire:model="gown_size" class="input">
                            <option value="">Pilih ukuran</option>
                            @foreach (['S', 'M', 'L', 'XL', 'XXL'] as $size)<option value="{{ $size }}">{{ $size }}</option>@endforeach
                        </select>
                    </x-field>
                </div>
            </x-card>

            <p class="text-sm text-text-muted">Data ini akan dicocokkan BAAK dengan transkrip dan berita acara sidang. Pastikan isiannya benar.</p>

            <div class="flex flex-wrap gap-2">
                <x-button type="submit" wire:loading.attr="disabled" wire:target="save">Kirim pendaftaran</x-button>
                <x-button :href="route('wisuda.overview', ['periode' => $period->id])" wire:navigate variant="secondary">Batal</x-button>
            </div>
        </form>
    @endif
</div>
