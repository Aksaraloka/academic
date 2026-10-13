<div class="space-y-6">
    <x-page-header title="Rekap kehadiran" subtitle="Kehadiran per mata kuliah dan syarat mengikuti ujian ({{ $minPercent }}%)." />

    @if (isset($mine))
        @forelse ($mine as $row)
            <x-card :title="$row['student']->name" wire:key="mine-{{ $row['student']->id }}">
                <x-progress label="Kehadiran keseluruhan" :value="$row['summary']['counts']['hadir'] + $row['summary']['counts']['terlambat']" :max="$row['summary']['total']" empty="Belum ada presensi" />
                <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    @foreach (auth()->user()->attendanceStatuses() as $status)
                        <div class="rounded-lg bg-surface-muted p-3">
                            <x-status-badge :status="$status" />
                            <p class="mt-2 text-2xl font-bold leading-none tabular-nums">{{ $row['summary']['counts'][$status->value] }}</p>
                        </div>
                    @endforeach
                </div>

                <h3 class="mt-6 text-base font-semibold">Per mata kuliah</h3>
                <ul class="mt-2 divide-y divide-border">
                    @forelse ($row['subjects'] as $subjectRow)
                        <li class="space-y-2 py-3" wire:key="sub-{{ $row['student']->id }}-{{ $subjectRow['subject']->id }}">
                            <div class="flex items-center justify-between gap-3">
                                <span class="min-w-0 truncate text-sm font-medium">{{ $subjectRow['subject']->name }}</span>
                                @if ($subjectRow['percent'] >= $minPercent)
                                    <x-badge variant="success" icon="check-circle">Memenuhi syarat ujian</x-badge>
                                @else
                                    <x-badge variant="danger" icon="exclamation-triangle">Di bawah {{ $minPercent }}%</x-badge>
                                @endif
                            </div>
                            <x-progress label="Kehadiran" :value="$subjectRow['attended']" :max="$subjectRow['total']" />
                        </li>
                    @empty
                        <li class="py-3 text-sm text-text-muted">Belum ada pertemuan yang tercatat.</li>
                    @endforelse
                </ul>
            </x-card>
        @empty
            <x-card><x-empty-state icon="chart-bar" title="Belum ada data kehadiran" description="Data akan muncul setelah akunmu tertaut ke data mahasiswa." /></x-card>
        @endforelse
    @else
        <div class="grid gap-3 sm:grid-cols-4">
            <div class="sm:col-span-2">
                <label for="group" class="sr-only">Kelas</label>
                <select id="group" wire:model.live="groupId" class="input">
                    @foreach ($groups as $option)
                        <option value="{{ $option->id }}" @selected($group?->id === $option->id)>{{ $option->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="matkul" class="sr-only">Mata kuliah</label>
                <select id="matkul" wire:model.live="subjectId" class="input">
                    <option value="">Semua mata kuliah</option>
                    @foreach ($subjects as $subject)<option value="{{ $subject->id }}">{{ $subject->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label for="bulan" class="sr-only">Bulan</label>
                <input id="bulan" type="month" wire:model.live="month" class="input" aria-describedby="bulan-hint">
                <p id="bulan-hint" class="mt-1 text-xs text-text-muted">Kosongkan untuk satu semester.</p>
            </div>
        </div>

        @if ($group)
            @if ($repeated->isNotEmpty())
                <x-card :title="'Perlu tindak lanjut (alpa ≥ '.$threshold.' pertemuan)'" :padding="false">
                    @foreach ($repeated as $row)
                        <div class="flex min-h-12 items-center gap-3 border-t border-border px-4 py-2 first:border-t-0">
                            <x-icon name="exclamation-triangle" class="text-warning" />
                            <span class="min-w-0 flex-1 truncate text-sm font-medium">{{ $row['student']->name }}</span>
                            <x-badge variant="danger">Alpa {{ $row['days'] }} hari</x-badge>
                        </div>
                    @endforeach
                </x-card>
            @endif

            <x-card :title="$group->name" :padding="false">
                <x-slot:actions>
                    <x-button size="sm" variant="secondary" icon="arrow-down-tray" :href="route('reports.export', array_filter(['group' => $group->id, 'matkul' => $subjectId, 'bulan' => $month]))">Ekspor CSV</x-button>
                </x-slot:actions>

                @if ($recap->isEmpty())
                    <x-empty-state icon="users" title="Kelas belum punya mahasiswa" description="Tempatkan mahasiswa lebih dulu di halaman Kelas." />
                @else
                    <table class="table-responsive">
                        <thead>
                            <tr>
                                <th scope="col">Nama</th>
                                <th scope="col">NIM</th>
                                @foreach (auth()->user()->attendanceStatuses() as $status)<th scope="col">{{ $status->label() }}</th>@endforeach
                                <th scope="col">Syarat ujian</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recap as $row)
                                <tr wire:key="r-{{ $row['student']->id }}">
                                    <td data-label="Nama" class="font-medium">{{ $row['student']->name }}</td>
                                    <td data-label="NIM" class="tabular-nums text-text-muted">{{ $row['student']->nim }}</td>
                                    @foreach (auth()->user()->attendanceStatuses() as $status)
                                        <td data-label="{{ $status->label() }}" class="tabular-nums">{{ $row['counts'][$status->value] }}</td>
                                    @endforeach
                                    <td data-label="Syarat ujian">
                                        @if ($row['percent'] === null)
                                            <span class="text-text-muted">Belum ada data</span>
                                        @else
                                            <x-badge :variant="$row['percent'] >= $minPercent ? 'success' : 'danger'" :icon="$row['percent'] >= $minPercent ? 'check-circle' : 'exclamation-triangle'"><span class="tabular-nums">{{ $row['percent'] }}%</span> · {{ $row['percent'] >= $minPercent ? 'Memenuhi' : 'Belum memenuhi' }}</x-badge>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </x-card>
        @else
            <x-card><x-empty-state icon="user-group" title="Belum ada kelas" description="Rekap muncul setelah kelas dan presensi tersedia." /></x-card>
        @endif
    @endif
</div>
