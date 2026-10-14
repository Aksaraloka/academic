<div class="space-y-6">
    <x-page-header title="Magang dan MBKM mahasiswa" :subtitle="$manager ? 'Setujui pengajuan, tetapkan dosen pembimbing, dan tutup program dengan nilai akhir.' : 'Mahasiswa yang kamu bimbing. Tutup program dengan nilai akhir setelah selesai.'">
        @if ($manager)
            <x-slot:actions>
                <x-button :href="route('conversions.index')" wire:navigate variant="secondary" icon="arrow-path">Konversi SKS</x-button>
            </x-slot:actions>
        @endif
    </x-page-header>

    @foreach (['decision', 'complete', 'note', 'supervisorId'] as $key)@error($key)<p class="rounded-lg border border-danger/40 bg-danger/10 p-3 text-sm text-danger" role="alert">{{ $message }}</p>@enderror @endforeach

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-field label="Status" name="status">
            <select id="status" wire:model.live="status" class="input">
                <option value="">Semua status</option>
                @foreach ($statuses as $option)<option value="{{ $option->value }}">{{ $option->label() }} ({{ $counts[$option->value] ?? 0 }})</option>@endforeach
            </select>
        </x-field>
        <x-field label="Cari" name="search"><input id="search" type="search" wire:model.live.debounce.300ms="search" class="input" placeholder="Mitra, nama, atau NIM"></x-field>
    </div>

    @if ($reviewing)
        <x-card title="Tinjau program">
            <x-info-list :items="array_filter([
                'Mahasiswa' => $reviewing->student->name.' · '.$reviewing->student->nim,
                'Program studi' => $reviewing->student->program?->label(),
                'Jenis program' => $reviewing->type->label(),
                'Mitra' => $reviewing->partner_name,
                'Posisi / peran' => $reviewing->position,
                'Lokasi' => $reviewing->location,
                'Pembimbing lapangan' => $reviewing->field_supervisor,
                'Periode' => $reviewing->periodLabel(),
                'SKS diharapkan' => $reviewing->proposed_credits,
                'Uraian' => $reviewing->description,
            ])" />

            @if ($manager && $reviewing->status === \App\Enums\InternshipStatus::Submitted)
                <div class="mt-4 grid max-w-xl gap-4 sm:grid-cols-2">
                    <x-field label="Dosen pembimbing" name="supervisorId" required>
                        <select id="supervisorId" wire:model="supervisorId" class="input"><option value="">Pilih dosen</option>@foreach ($teachers as $teacher)<option value="{{ $teacher->id }}">{{ $teacher->name }}</option>@endforeach</select>
                    </x-field>
                    <x-field label="Catatan" name="note" hint="Wajib diisi bila menolak."><input id="note" type="text" wire:model="note" class="input" maxlength="500"></x-field>
                </div>
                <div class="mt-4 flex flex-wrap gap-2">
                    <x-button wire:click="approve" wire:loading.attr="disabled" wire:target="approve">Setujui</x-button>
                    <x-button variant="secondary" wire:click="reject">Tolak</x-button>
                    <x-button variant="ghost" wire:click="$set('reviewingId', null)">Tutup</x-button>
                </div>
            @elseif ($reviewing->status === \App\Enums\InternshipStatus::Approved)
                <p class="mt-4 text-sm text-text-muted">Pembimbing: {{ $reviewing->supervisor?->name ?? '–' }}. Tutup program setelah mahasiswa menyelesaikannya.</p>
                <div class="mt-3 grid max-w-xl gap-4 sm:grid-cols-2">
                    <x-field label="Nilai akhir" name="letter" required>
                        <select id="letter" wire:model="letter" class="input">@foreach ($letters as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach</select>
                    </x-field>
                    <x-field label="Evaluasi" name="note"><input id="note" type="text" wire:model="note" class="input" maxlength="1000"></x-field>
                </div>
                <div class="mt-4 flex flex-wrap gap-2">
                    <x-button wire:click="complete" wire:confirm="Tutup program dan catat nilai akhir?" wire:loading.attr="disabled" wire:target="complete">Tutup program</x-button>
                    <x-button variant="ghost" wire:click="$set('reviewingId', null)">Tutup panel</x-button>
                </div>
            @else
                <div class="mt-4"><x-button variant="ghost" wire:click="$set('reviewingId', null)">Tutup</x-button></div>
            @endif
        </x-card>
    @endif

    <x-card :padding="false">
        @forelse ($internships as $internship)
            <div class="flex min-h-14 flex-wrap items-center gap-3 border-t border-border px-4 py-3 first:border-t-0" wire:key="intr-{{ $internship->id }}">
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium">{{ $internship->student->name }} · {{ $internship->partner_name }}</p>
                    <p class="truncate text-xs text-text-muted">{{ $internship->student->nim }} · {{ $internship->type->label() }} · {{ $internship->periodLabel() }}@if ($internship->supervisor) · {{ $internship->supervisor->name }}@endif</p>
                </div>
                @if ($internship->final_letter)<x-badge variant="success">Nilai {{ $internship->final_letter }}</x-badge>@endif
                <x-badge :variant="$internship->status->variant()">{{ $internship->status->label() }}</x-badge>
                @if (($manager && $internship->status === \App\Enums\InternshipStatus::Submitted) || $internship->status === \App\Enums\InternshipStatus::Approved)
                    <x-button size="sm" wire:click="review({{ $internship->id }})">{{ $internship->status === \App\Enums\InternshipStatus::Submitted ? 'Tinjau' : 'Kelola' }}</x-button>
                @endif
            </div>
        @empty
            <x-empty-state icon="building-office" title="Tidak ada pengajuan" description="Pengajuan magang dan MBKM muncul di sini." />
        @endforelse
    </x-card>
</div>
