<div class="space-y-6">
    <x-page-header title="Jurnal perkuliahan" :subtitle="$isStudent ?? false ? 'Materi yang diajarkan tiap pertemuan di mata kuliahmu.' : 'BAP: materi yang diajarkan tiap pertemuan, diisi dosen bersama presensi.'" />

    @if (! $term)
        <x-card><x-empty-state icon="calendar-days" title="Belum ada semester" description="Admin perlu menambahkan semester akademik lebih dulu." /></x-card>
    @else
        <div class="max-w-xs">
            <x-field label="Semester" name="termId">
                <select id="termId" wire:model.live="termId" class="input">
                    @foreach ($terms as $option)<option value="{{ $option->id }}" @selected($option->id === $term->id)>{{ $option->name }} · {{ $option->semester }}{{ $option->is_active ? ' (aktif)' : '' }}</option>@endforeach
                </select>
            </x-field>
        </div>

        <x-card :padding="false">
            @forelse ($rows as $row)
                @php $assignment = $row['assignment']; $complete = $row['held'] > 0 && $row['filled'] >= $row['held']; @endphp
                <a href="{{ route('journals.show', $assignment) }}" wire:navigate wire:key="j-{{ $assignment->id }}" class="flex min-h-14 flex-wrap items-center gap-3 border-t border-border px-4 py-3 first:border-t-0 hover:bg-surface-muted">
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium">{{ $assignment->subject->code }} · {{ $assignment->subject->name }}</p>
                        <p class="truncate text-xs text-text-muted">{{ $assignment->teacher->name }} · Kelas {{ $assignment->classLabel() }}</p>
                    </div>
                    <span class="text-xs text-text-muted tabular-nums">{{ $row['filled'] }} dari {{ $row['held'] }} pertemuan terisi</span>
                    @if ($row['held'] === 0)
                        <x-badge>Belum ada pertemuan</x-badge>
                    @elseif ($complete)
                        <x-badge variant="success" icon="check-circle">Lengkap</x-badge>
                    @else
                        <x-badge variant="warning">{{ $row['held'] - $row['filled'] }} belum diisi</x-badge>
                    @endif
                </a>
            @empty
                <x-empty-state icon="clipboard-document-list" title="Belum ada kuliah" description="Kuliah semester ini muncul di sini." />
            @endforelse
        </x-card>
    @endif
</div>
