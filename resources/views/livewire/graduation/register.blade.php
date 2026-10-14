<div class="space-y-6">
    <x-page-header :title="'Daftar wisuda'" :subtitle="$period->name">
        <x-slot:actions>
            <x-button :href="route('graduation.overview', ['period' => $period->id])" wire:navigate variant="secondary" icon="arrow-left">Kembali</x-button>
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

        <x-card title="Kelayakan dari transkrip">
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ([
                    ['IPK', $eligibility['gpa'] === null ? '–' : number_format($eligibility['gpa'], 2, ',', '.'), 'minimal '.number_format($eligibility['min_gpa'], 2, ',', '.'), $eligibility['meets_gpa']],
                    ['SKS lulus', (string) $eligibility['credits'], 'minimal '.$eligibility['min_credits'], $eligibility['meets_credits']],
                ] as [$label, $value, $minimum, $met])
                    <div class="flex items-center justify-between gap-3 rounded-lg bg-surface-muted p-3">
                        <div>
                            <p class="text-xs font-medium text-text-muted">{{ $label }} ({{ $minimum }})</p>
                            <p class="text-2xl font-bold tabular-nums">{{ $value }}</p>
                        </div>
                        @if ($met)
                            <x-badge variant="success" icon="check-circle">Terpenuhi</x-badge>
                        @else
                            <x-badge variant="danger" icon="x-circle">Belum</x-badge>
                        @endif
                    </div>
                @endforeach
            </div>
            <p class="mt-3 text-xs text-text-muted">Dihitung otomatis dari nilai yang sudah dikunci dosen, termasuk nilai awal dan hasil terbaik untuk mata kuliah yang diulang. <a href="{{ route('grades.transcript') }}" wire:navigate class="font-medium text-primary hover:underline">Lihat transkrip</a></p>
            @error('eligibility')<p class="mt-3 rounded-lg border border-danger bg-danger/15 p-3 text-sm text-danger" role="alert">{{ $message }}</p>@enderror
            @unless ($eligibility['eligible'])
                <p class="mt-3 text-sm text-warning" role="status">Kamu belum memenuhi syarat akademik, jadi pendaftaran belum bisa dikirim.</p>
            @endunless
        </x-card>

        <form wire:submit="save" class="space-y-4">
            <x-card title="Data akademik">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <x-field label="Judul skripsi / tugas akhir" name="thesis_title" required>
                            <textarea id="thesis_title" wire:model="thesis_title" rows="3" class="input" @error('thesis_title') aria-invalid="true" @enderror></textarea>
                        </x-field>
                    </div>
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
                <x-button type="submit" wire:loading.attr="disabled" wire:target="save" :disabled="! $eligibility['eligible']">Kirim pendaftaran</x-button>
                <x-button :href="route('graduation.overview', ['period' => $period->id])" wire:navigate variant="secondary">Batal</x-button>
            </div>
        </form>
    @endif
</div>
