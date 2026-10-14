<div class="space-y-6">
    <x-page-header title="Tugas akhir mahasiswa" :subtitle="$isTeacher ? 'Mahasiswa yang kamu bimbing atau uji.' : 'Pengajuan judul, pembimbing, dan jadwal sidang seluruh mahasiswa.'" />

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-field label="Status" name="status">
            <select id="status" wire:model.live="status" class="input">
                <option value="">Semua status</option>
                @foreach ($statuses as $option)<option value="{{ $option->value }}">{{ $option->label() }} ({{ $counts[$option->value] ?? 0 }})</option>@endforeach
            </select>
        </x-field>
        <x-field label="Cari" name="search"><input id="search" type="search" wire:model.live.debounce.300ms="search" class="input" placeholder="Judul, nama, atau NIM"></x-field>
    </div>

    <x-card :padding="false">
        @forelse ($theses as $thesis)
            <a href="{{ route('thesis.show', $thesis) }}" wire:navigate wire:key="th-{{ $thesis->id }}" class="flex min-h-14 flex-wrap items-center gap-3 border-t border-border px-4 py-3 first:border-t-0 hover:bg-surface-muted">
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium">{{ $thesis->title }}</p>
                    <p class="truncate text-xs text-text-muted">{{ $thesis->student->name }} · {{ $thesis->student->nim }} · {{ $thesis->supervisors->map(fn ($s) => $s->teacher->name)->implode(', ') ?: 'Pembimbing belum ditetapkan' }}</p>
                </div>
                @if ($thesis->latestDefense && $thesis->status === \App\Enums\ThesisStatus::DefenseScheduled)<span class="text-xs text-text-muted">Sidang {{ $thesis->latestDefense->scheduled_on->translatedFormat('j M Y') }}</span>@endif
                <x-badge :variant="$thesis->status->variant()">{{ $thesis->status->label() }}</x-badge>
            </a>
        @empty
            <x-empty-state icon="academic-cap" title="Belum ada tugas akhir" description="Pengajuan judul mahasiswa muncul di sini." />
        @endforelse
    </x-card>
</div>
