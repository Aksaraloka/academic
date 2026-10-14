<div class="space-y-6">
    <x-page-header title="Permohonan surat" subtitle="Surat keterangan, transkrip sementara, rekomendasi, dan izin penelitian. Surat terbit sebagai PDF dengan nomor dan QR verifikasi." />

    @error('purpose')<p class="rounded-lg border border-danger/40 bg-danger/10 p-3 text-sm text-danger" role="alert">{{ $message }}</p>@enderror

    <form wire:submit="submit">
        <x-card title="Ajukan surat">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <x-field label="Jenis surat" name="type" required hint="{{ $selected->description() }}">
                        <select id="type" wire:model.live="type" class="input">
                            @foreach ($types as $option)<option value="{{ $option->value }}">{{ $option->label() }}</option>@endforeach
                        </select>
                    </x-field>
                </div>

                <div class="sm:col-span-2">
                    <x-field :label="$selected->purposeLabel()" name="purpose" required>
                        <input id="purpose" type="text" wire:model="purpose" class="input" maxlength="200" @error('purpose') aria-invalid="true" @enderror
                            placeholder="{{ match ($selected) { \App\Enums\LetterType::ActiveStudent => 'Mis. Pengurusan beasiswa', \App\Enums\LetterType::ProvisionalTranscript => 'Mis. Melamar magang', \App\Enums\LetterType::Recommendation => 'Mis. Beasiswa pertukaran pelajar', \App\Enums\LetterType::ResearchPermit => 'Judul atau topik penelitian' } }}">
                    </x-field>
                </div>

                @if ($selected === \App\Enums\LetterType::Recommendation)
                    <x-field label="Ditujukan kepada" name="addressee" hint="Opsional. Instansi atau panitia penerima."><input id="addressee" type="text" wire:model="addressee" class="input"></x-field>
                    <x-field label="Dosen perekomendasi" name="recommenderId" hint="Opsional. Dosen yang mengenal kinerjamu.">
                        <select id="recommenderId" wire:model="recommenderId" class="input">
                            <option value="">Atas nama program studi</option>
                            @foreach ($teachers as $teacher)<option value="{{ $teacher->id }}">{{ $teacher->name }}</option>@endforeach
                        </select>
                    </x-field>
                @endif

                @if ($selected === \App\Enums\LetterType::ResearchPermit)
                    <x-field label="Instansi tujuan" name="institution" required><input id="institution" type="text" wire:model="institution" class="input" placeholder="Mis. Dinas Pendidikan Kota Bandung"></x-field>
                    <x-field label="Ditujukan kepada" name="addressee" required><input id="addressee" type="text" wire:model="addressee" class="input" placeholder="Mis. Kepala Dinas"></x-field>
                    <x-field label="Penelitian mulai" name="period_start" required><input id="period_start" type="date" wire:model="period_start" class="input"></x-field>
                    <x-field label="Penelitian selesai" name="period_end" required><input id="period_end" type="date" wire:model="period_end" class="input"></x-field>
                @endif
            </div>
            <div class="mt-4"><x-button type="submit" wire:loading.attr="disabled" wire:target="submit">{{ $selected->requiresApproval() ? 'Kirim permohonan' : 'Terbitkan surat' }}</x-button></div>
        </x-card>
    </form>

    <x-card title="Riwayat permohonan" :padding="false">
        @forelse ($letters as $letter)
            <div class="flex flex-wrap items-center gap-3 border-t border-border px-4 py-3 first:border-t-0" wire:key="lt-{{ $letter->id }}">
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium">{{ $letter->type->label() }}</p>
                    <p class="truncate text-xs text-text-muted">{{ $letter->purpose }} · diajukan {{ $letter->created_at->translatedFormat('j M Y') }}@if ($letter->number) · No. {{ $letter->number }}@endif</p>
                    @if ($letter->decision_note)<p class="mt-1 text-xs {{ $letter->status === \App\Enums\LetterStatus::Rejected ? 'text-danger' : 'text-text-muted' }}">Catatan staf: {{ $letter->decision_note }}</p>@endif
                </div>
                <x-badge :variant="$letter->status->variant()">{{ $letter->status->label() }}</x-badge>
                @if ($letter->isIssued())
                    <x-button size="sm" :href="route('letters.pdf', $letter)" icon="arrow-down-tray" download>PDF</x-button>
                @elseif ($letter->status === \App\Enums\LetterStatus::Submitted)
                    <x-button size="sm" variant="secondary" wire:click="cancel({{ $letter->id }})" wire:confirm="Batalkan permohonan ini?">Batalkan</x-button>
                @endif
            </div>
        @empty
            <x-empty-state icon="envelope" title="Belum ada permohonan" description="Surat yang kamu ajukan muncul di sini beserta tombol unduh." />
        @endforelse
    </x-card>
</div>
