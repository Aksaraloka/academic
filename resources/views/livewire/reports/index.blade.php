<div class="space-y-6">
    <x-page-header title="Rekap kehadiran" subtitle="Ringkasan Hadir, Terlambat, Izin, Sakit, dan Alpa." />

    @if (isset($mine))
        @forelse ($mine as $row)
            <x-card :title="$row['student']->name" wire:key="mine-{{ $row['student']->id }}">
                <x-progress label="Kehadiran" :value="$row['summary']['counts']['hadir'] + $row['summary']['counts']['terlambat']" :max="$row['summary']['total']" empty="Belum ada presensi" />
                <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-5">
                    @foreach (\App\Enums\AttendanceStatus::cases() as $status)
                        <div class="rounded-lg bg-surface-muted p-3">
                            <x-status-badge :status="$status" />
                            <p class="mt-2 text-2xl font-bold leading-none tabular-nums">{{ $row['summary']['counts'][$status->value] }}</p>
                        </div>
                    @endforeach
                </div>
            </x-card>
        @empty
            <x-card><x-empty-state icon="chart-bar" title="Belum ada data kehadiran" description="Data akan muncul setelah akunmu tertaut ke data siswa." /></x-card>
        @endforelse
    @else
        <div class="grid gap-3 sm:grid-cols-3">
            <div class="sm:col-span-2">
                <label for="group" class="sr-only">Rombel</label>
                <select id="group" wire:model.live="groupId" class="input">
                    @foreach ($groups as $option)
                        <option value="{{ $option->id }}" @selected($group?->id === $option->id)>{{ $option->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="bulan" class="sr-only">Bulan</label>
                <input id="bulan" type="month" wire:model.live="month" class="input">
            </div>
        </div>

        @if ($group)
            @if ($repeated->isNotEmpty())
                <x-card :title="'Perlu tindak lanjut (alpa ≥ '.$threshold.' hari)'" :padding="false">
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
                    <x-button size="sm" variant="secondary" icon="arrow-down-tray" :href="route('reports.export', ['group' => $group->id, 'bulan' => $month])">Ekspor CSV</x-button>
                </x-slot:actions>

                @if ($recap->isEmpty())
                    <x-empty-state icon="users" title="Rombel belum punya siswa" description="Tempatkan siswa lebih dulu di halaman Rombel." />
                @else
                    <table class="table-responsive">
                        <thead>
                            <tr>
                                <th scope="col">Nama</th>
                                @foreach (\App\Enums\AttendanceStatus::cases() as $status)<th scope="col">{{ $status->label() }}</th>@endforeach
                                <th scope="col">Kehadiran</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recap as $row)
                                <tr wire:key="r-{{ $row['student']->id }}">
                                    <td data-label="Nama" class="font-medium">{{ $row['student']->name }}</td>
                                    @foreach (\App\Enums\AttendanceStatus::cases() as $status)
                                        <td data-label="{{ $status->label() }}" class="tabular-nums">{{ $row['counts'][$status->value] }}</td>
                                    @endforeach
                                    <td data-label="Kehadiran">
                                        @if ($row['percent'] === null)
                                            <span class="text-text-muted">Belum ada data</span>
                                        @else
                                            <x-badge :variant="$row['percent'] >= $minPercent ? 'success' : 'danger'" :icon="$row['percent'] >= $minPercent ? 'check-circle' : 'exclamation-triangle'"><span class="tabular-nums">{{ $row['percent'] }}%</span></x-badge>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </x-card>
        @else
            <x-card><x-empty-state icon="user-group" title="Belum ada rombel" description="Rekap muncul setelah rombel dan presensi tersedia." /></x-card>
        @endif
    @endif
</div>
