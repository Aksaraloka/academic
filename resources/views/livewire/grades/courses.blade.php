<div class="space-y-6">
    <x-page-header title="Input nilai" subtitle="Kelas yang kamu ampu. Isi nilai tugas, UTS, dan UAS, lalu kunci sebelum batas waktu.">
        <x-slot:actions>
            <x-button :href="route('grades.revisions')" wire:navigate variant="secondary" icon="arrow-path">Revisi nilai</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="max-w-xs">
        <label for="semester" class="mb-1 block text-xs font-medium text-text-muted">Semester</label>
        <select id="semester" wire:model.live="termId" class="input">
            @foreach ($terms as $option)
                <option value="{{ $option->id }}">{{ $option->name }} · {{ $option->semester }}{{ $option->is_active ? ' (aktif)' : '' }}</option>
            @endforeach
        </select>
    </div>

    @if (! $hasTeacher)
        <x-card><x-empty-state icon="identification" title="Akunmu belum tertaut ke data dosen" description="Minta BAAK menautkan akunmu agar kelas yang kamu ampu tampil di sini." /></x-card>
    @elseif ($rows->isEmpty())
        <x-card><x-empty-state icon="clipboard-document-list" title="Tidak ada kelas pada semester ini" description="Kelas muncul setelah kamu ditugaskan mengampu mata kuliah pada jadwal." /></x-card>
    @else
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($rows as $row)
                @php $assignment = $row['assignment']; @endphp
                <x-card wire:key="course-{{ $assignment->id }}">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <h2 class="truncate text-base font-semibold">{{ $assignment->subject->name }}</h2>
                            <p class="truncate text-xs text-text-muted">{{ $assignment->subject->code }}@if ($assignment->subject->credits) · {{ $assignment->subject->credits }} SKS @endif· Kelas {{ $assignment->studyGroup->name }}</p>
                        </div>
                        @if ($row['locked'])
                            <x-badge variant="success" icon="lock-closed">Terkunci</x-badge>
                        @elseif ($row['started'])
                            <x-badge variant="warning" icon="pencil-square">Draf</x-badge>
                        @else
                            <x-badge icon="clock">Belum dimulai</x-badge>
                        @endif
                    </div>

                    <div class="mt-4">
                        <x-progress label="Nilai lengkap" :value="$row['complete']" :max="$row['students']" empty="Belum ada mahasiswa" />
                    </div>

                    <p class="mt-3 text-xs {{ $row['overdue'] ? 'font-semibold text-danger' : ($row['daysLeft'] !== null && $row['daysLeft'] <= 7 && ! $row['locked'] ? 'font-semibold text-warning' : 'text-text-muted') }}">
                        @if ($row['deadline'])
                            Batas input {{ $row['deadline']->translatedFormat('j F Y') }}
                            @if ($row['locked'])
                            @elseif ($row['overdue']) · sudah lewat
                            @else · {{ $row['daysLeft'] <= 0 ? 'hari ini' : $row['daysLeft'].' hari lagi' }}
                            @endif
                        @else
                            Batas input belum ditentukan
                        @endif
                    </p>

                    <div class="mt-4">
                        <x-button :href="route('grades.input.sheet', $assignment)" wire:navigate size="sm" :variant="$row['locked'] ? 'secondary' : 'primary'" icon="pencil-square">{{ $row['locked'] ? 'Lihat nilai' : 'Input nilai' }}</x-button>
                    </div>
                </x-card>
            @endforeach
        </div>
    @endif
</div>
