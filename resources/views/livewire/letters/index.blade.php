<div class="space-y-6">
    <x-page-header title="Permohonan surat mahasiswa" subtitle="Surat rekomendasi dan izin penelitian menunggu persetujuanmu. Surat aktif kuliah dan transkrip sementara terbit otomatis." />

    @foreach (['note', 'decision'] as $key)@error($key)<p class="rounded-lg border border-danger/40 bg-danger/10 p-3 text-sm text-danger" role="alert">{{ $message }}</p>@enderror @endforeach

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-field label="Status" name="status">
            <select id="status" wire:model.live="status" class="input">
                <option value="">Semua status</option>
                @foreach ($statuses as $option)<option value="{{ $option->value }}">{{ $option->label() }} ({{ $counts[$option->value] ?? 0 }})</option>@endforeach
            </select>
        </x-field>
        <x-field label="Jenis" name="type">
            <select id="type" wire:model.live="type" class="input">
                <option value="">Semua jenis</option>
                @foreach ($types as $option)<option value="{{ $option->value }}">{{ $option->label() }}</option>@endforeach
            </select>
        </x-field>
        <x-field label="Cari" name="search"><input id="search" type="search" wire:model.live.debounce.300ms="search" class="input" placeholder="Nama, NIM, keperluan, atau nomor"></x-field>
    </div>

    @if ($reviewing)
        <x-card title="Tinjau permohonan">
            <x-info-list :items="array_filter([
                'Mahasiswa' => $reviewing->student->name.' · '.$reviewing->student->nim,
                'Program studi' => $reviewing->student->program?->label(),
                'Jenis surat' => $reviewing->type->label(),
                $reviewing->type->purposeLabel() => $reviewing->purpose,
                'Ditujukan kepada' => $reviewing->details['addressee'] ?? null,
                'Instansi' => $reviewing->details['institution'] ?? null,
                'Periode penelitian' => isset($reviewing->details['period_start']) ? \Illuminate\Support\Carbon::parse($reviewing->details['period_start'])->translatedFormat('j M Y').' – '.\Illuminate\Support\Carbon::parse($reviewing->details['period_end'])->translatedFormat('j M Y') : null,
            ])" />
            <div class="mt-4 max-w-xl">
                <x-field label="Catatan" name="note" hint="Wajib diisi bila menolak."><input id="note" type="text" wire:model="note" class="input" maxlength="500"></x-field>
            </div>
            <div class="mt-4 flex flex-wrap gap-2">
                <x-button wire:click="approve" wire:confirm="Setujui dan terbitkan surat ini?" wire:loading.attr="disabled" wire:target="approve">Setujui dan terbitkan</x-button>
                <x-button variant="secondary" wire:click="reject">Tolak</x-button>
                <x-button variant="ghost" wire:click="$set('reviewingId', null)">Tutup</x-button>
            </div>
        </x-card>
    @endif

    <x-card :padding="false">
        @forelse ($letters as $letter)
            <div class="flex min-h-14 flex-wrap items-center gap-3 border-t border-border px-4 py-3 first:border-t-0" wire:key="ltr-{{ $letter->id }}">
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium">{{ $letter->type->label() }} · {{ $letter->student->name }}</p>
                    <p class="truncate text-xs text-text-muted">{{ $letter->student->nim }} · {{ $letter->purpose }} · {{ $letter->created_at->translatedFormat('j M Y') }}@if ($letter->number) · No. {{ $letter->number }}@endif</p>
                </div>
                <x-badge :variant="$letter->status->variant()">{{ $letter->status->label() }}</x-badge>
                @if ($letter->status === \App\Enums\LetterStatus::Submitted)
                    <x-button size="sm" wire:click="review({{ $letter->id }})">Tinjau</x-button>
                @elseif ($letter->isIssued())
                    <x-button size="sm" variant="secondary" :href="route('letters.pdf', $letter)" icon="arrow-down-tray" download>PDF</x-button>
                @endif
            </div>
        @empty
            <x-empty-state icon="envelope" title="Tidak ada permohonan" description="Permohonan surat mahasiswa muncul di sini." />
        @endforelse
    </x-card>
</div>
