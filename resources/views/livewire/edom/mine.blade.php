<div class="mx-auto max-w-4xl space-y-6">
    <x-page-header title="Evaluasi dosen" subtitle="Nilai pengajaran dosen di tiap mata kuliah yang kamu ikuti. Jawabanmu anonim.">
        <x-slot:actions>
            <x-button :href="route('grades.khs')" wire:navigate variant="secondary" icon="chart-bar">KHS</x-button>
        </x-slot:actions>
    </x-page-header>

    @if (! $term || ! $period)
        <x-card><x-empty-state icon="chart-bar" title="Belum ada periode evaluasi" description="Evaluasi dosen muncul di sini saat periodenya diterbitkan BAAK." /></x-card>
    @else
        <x-card>
            <div class="flex flex-wrap items-center gap-3">
                <x-badge :variant="$period->phaseVariant()">{{ $period->phaseLabel() }}</x-badge>
                <span class="text-sm text-text-muted">{{ $period->opens_on->translatedFormat('j M') }} – {{ $period->closes_on->translatedFormat('j F Y') }} · selesai {{ $done->count() }} dari {{ $assignments->count() }} mata kuliah</span>
            </div>
            @if ($period->gates_grades)<p class="mt-2 text-sm text-text-muted">Evaluasi semua mata kuliah wajib diisi sebelum nilai semester ini bisa dilihat di KHS.</p>@endif
            <div class="mt-3"><x-progress label="Evaluasi selesai" :value="$done->count()" :max="$assignments->count()" /></div>
        </x-card>

        <x-card :padding="false">
            @forelse ($assignments as $assignment)
                @php $finished = $done->contains($assignment->id); @endphp
                <div class="flex min-h-14 flex-wrap items-center gap-3 border-t border-border px-4 py-3 first:border-t-0" wire:key="as-{{ $assignment->id }}">
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium">{{ $assignment->subject->code }} · {{ $assignment->subject->name }}</p>
                        <p class="truncate text-xs text-text-muted">{{ $assignment->teacher->name }} · Kelas {{ $assignment->classLabel() }}</p>
                    </div>
                    @if ($finished)
                        <x-badge variant="success" icon="check-circle">Sudah dievaluasi</x-badge>
                    @elseif ($period->isOpen())
                        <x-button size="sm" :href="route('evaluations.fill', $assignment)" wire:navigate>Isi evaluasi</x-button>
                    @else
                        <x-badge variant="warning">Belum diisi</x-badge>
                    @endif
                </div>
            @empty
                <x-empty-state icon="academic-cap" title="Belum ada mata kuliah" description="Mata kuliahmu muncul setelah KRS disetujui atau kelas ditetapkan." />
            @endforelse
        </x-card>
    @endif
</div>
