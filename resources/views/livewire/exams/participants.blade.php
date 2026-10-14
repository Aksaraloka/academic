<div class="space-y-6">
    <x-page-header :title="$exam->assignment->subject->name.' · '.$exam->type->label()" :subtitle="$exam->date->translatedFormat('l, j F Y').' · '.$exam->timeRange().' · '.($exam->room?->name ?? 'Ruang belum ditentukan').' · Kelas '.$exam->assignment->classLabel()">
        <x-slot:actions>
            <x-button :href="route('exams.index', ['term' => $exam->academic_term_id])" wire:navigate variant="secondary" icon="arrow-left">Jadwal ujian</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-4 sm:grid-cols-3">
        <x-stat-tile label="Peserta" :value="$rows->count()" />
        <x-stat-tile label="Berhak ikut ujian" :value="$eligibleCount" :note="'kehadiran minimal '.$minPercent.'%'" />
        <x-stat-tile label="Tidak berhak" :value="$rows->count() - $eligibleCount" />
    </div>

    <x-card :padding="false">
        @forelse ($rows as $row)
            <div class="flex min-h-14 flex-wrap items-center gap-3 border-t border-border px-4 py-3 first:border-t-0" wire:key="p-{{ $row['student']->id }}">
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium">{{ $row['student']->name }}</p>
                    <p class="truncate text-xs text-text-muted">{{ $row['student']->nim }} · hadir {{ $row['attended'] }} dari {{ $row['held'] }} pertemuan{{ $row['percent'] !== null ? ' ('.$row['percent'].'%)' : '' }}</p>
                </div>
                @if ($row['eligible'])
                    <x-badge variant="success" icon="check-circle">Berhak</x-badge>
                @else
                    <x-badge variant="danger">Tidak berhak</x-badge>
                    <span class="text-xs text-text-muted">{{ $row['reason'] }}</span>
                @endif
            </div>
        @empty
            <x-empty-state icon="users" title="Belum ada peserta" description="Peserta mengikuti anggota kelas dan KRS yang disetujui." />
        @endforelse
    </x-card>
</div>
