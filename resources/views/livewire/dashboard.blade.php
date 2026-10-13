<div class="space-y-6">
    <x-page-header
        :title="'Ringkasan hari ini'"
        :subtitle="$today->translatedFormat('l, j F Y').' · '.$role->label()" />

    @if (isset($overview))
        <section aria-labelledby="ringkasan" class="space-y-3">
            <h2 id="ringkasan" class="sr-only">Ringkasan hari ini</h2>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                <x-stat-tile label="Kehadiran mahasiswa hari ini" :value="$overview['percent'] === null ? '–' : $overview['percent'].'%'"
                    :note="$overview['percent'] === null ? 'Belum ada presensi' : $overview['present'].' dari '.$overview['total'].' mahasiswa'" />
                <x-stat-tile label="Pertemuan belum diisi" :value="$overview['unfilled']"
                    :note="$overview['unfilled'] > 0 ? 'Perlu ditindaklanjuti' : 'Semua sudah terisi'"
                    :tone="$overview['unfilled'] > 0 ? 'text-warning' : 'text-success'" />
                <x-stat-tile class="col-span-2 sm:col-span-1" label="Dosen belum check-in" :value="$notCheckedIn->count()"
                    :note="$notCheckedIn->count() > 0 ? 'Cek daftar di bawah' : 'Semua sudah check-in'"
                    :tone="$notCheckedIn->count() > 0 ? 'text-warning' : 'text-success'" />
            </div>
        </section>

        @if ($notCheckedIn->isNotEmpty())
            <x-card title="Belum check-in pagi ini" :padding="false">
                <ul class="divide-y divide-border">
                    @foreach ($notCheckedIn->take(8) as $teacher)
                        <li class="flex min-h-12 items-center gap-3 px-4 py-2.5">
                            <x-icon name="map-pin" class="text-primary" />
                            <span class="min-w-0 flex-1 truncate text-sm font-medium">{{ $teacher->name }}</span>
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @endif
    @endif

    @if (isset($checkin) || (isset($mySchedules) && ! isset($children) && ! isset($group)))
        @if (auth()->user()->teacher)
            <x-card :padding="false">
                <div class="flex min-h-14 items-center gap-3 px-4 py-2.5">
                    <x-icon name="map-pin" class="text-primary" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium">Check-in kehadiran</p>
                        <p class="truncate text-xs text-text-muted">
                            @if ($checkin?->check_in_at)
                                Masuk {{ $checkin->check_in_at->format('H.i') }}@if ($checkin->check_out_at), pulang {{ $checkin->check_out_at->format('H.i') }}@endif
                            @else
                                Kamu belum check-in hari ini.
                            @endif
                        </p>
                    </div>
                    @if (auth()->user()->hasRole(\App\Enums\Role::Teacher, \App\Enums\Role::Duty, \App\Enums\Role::Curriculum, \App\Enums\Role::Principal))
                        <x-button :href="route('checkin')" wire:navigate size="sm" variant="secondary">Buka</x-button>
                    @endif
                </div>
            </x-card>
        @endif
    @endif

    @if (isset($mySchedules))
        <x-card :title="isset($group) ? 'Jadwal hari ini · '.$group->name : 'Jadwal mengajar hari ini'" :padding="false">
            @forelse ($mySchedules as $schedule)
                @php
                    $session = isset($sessions) ? $sessions->get($schedule->id) : null;
                    $override = $schedule->overrides->first();
                @endphp
                <div class="flex min-h-14 items-center gap-3 border-t border-border px-4 py-2.5 first:border-t-0 hover:bg-surface-muted" wire:key="sch-{{ $schedule->id }}">
                    <x-icon name="clipboard-document-list" class="text-primary" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium">{{ $schedule->assignment->subject->name }} · {{ $schedule->assignment->studyGroup->name }}</p>
                        <p class="truncate text-xs text-text-muted">
                            Sesi ke-{{ $schedule->timeSlot->period_number }} · {{ $schedule->timeSlot->timeRange() }}@if ($schedule->room) · {{ $schedule->room->name }}@endif
                            @if ($override) · <span class="font-semibold text-warning">{{ $override->status === 'kosong' ? 'Sesi kosong' : 'Dosen pengganti: '.$override->substituteTeacher?->name }}</span>@endif
                        </p>
                    </div>
                    @if (isset($sessions))
                        @if ($session)
                            <x-button :href="route('attendance.mark', $session)" wire:navigate size="sm" variant="secondary">Lihat</x-button>
                        @else
                            <x-badge variant="warning" icon="clock">Belum diisi</x-badge>
                        @endif
                    @endif
                </div>
            @empty
                <x-empty-state icon="calendar-days" title="Tidak ada jadwal hari ini" description="Nikmati harimu. Jadwal berikutnya tampil di menu Jadwal." />
            @endforelse
        </x-card>

        @if (isset($sessions) && $mySchedules->isNotEmpty())
            <x-card title="Buka presensi" description="">
                <p class="text-sm text-text-muted">Pilih jadwal di menu Presensi untuk membuka sesi kelas.</p>
                <div class="mt-3"><x-button :href="route('attendance.index')" wire:navigate icon="clipboard-document-check">Ke halaman presensi</x-button></div>
            </x-card>
        @endif
    @endif

    @if (isset($homeroomGroups) && $homeroomGroups->isNotEmpty())
        <x-card title="Kelas perwalian saya" :padding="false">
            <ul class="divide-y divide-border">
                @foreach ($homeroomGroups as $homeroom)
                    <li class="flex min-h-14 items-center gap-3 px-4 py-2.5 hover:bg-surface-muted">
                        <x-icon name="user-group" class="text-primary" />
                        <span class="min-w-0 flex-1 truncate text-sm font-medium">{{ $homeroom->name }}</span>
                        <x-button :href="route('reports.index', ['group' => $homeroom->id])" wire:navigate size="sm" variant="secondary">Rekap</x-button>
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endif

    @if (isset($summary))
        <x-card title="Kehadiranku">
            <x-progress label="Kehadiran" :value="$summary['counts']['hadir'] + $summary['counts']['terlambat']" :max="$summary['total']" empty="Belum ada presensi" />
            <div class="mt-4 flex flex-wrap gap-2">
                @foreach (auth()->user()->attendanceStatuses() as $status)
                    <x-status-badge :status="$status" /> <span class="mr-2 text-sm font-semibold tabular-nums">{{ $summary['counts'][$status->value] }}</span>
                @endforeach
            </div>
        </x-card>
    @endif

    @if (isset($children))
        <section class="space-y-4" aria-label="Anak saya">
            @forelse ($children as $child)
                <x-card :title="$child->name" wire:key="child-{{ $child->id }}">
                    @php $recordsToday = $todayRecords->get($child->id, collect()); @endphp
                    @if ($recordsToday->isEmpty())
                        <p class="text-sm text-text-muted">Belum ada presensi hari ini.</p>
                    @else
                        <ul class="space-y-2">
                            @foreach ($recordsToday as $record)
                                <li class="flex items-center justify-between gap-3 text-sm">
                                    <span class="min-w-0 truncate">{{ $record->session->schedule?->assignment?->subject?->name ?? 'Presensi harian' }}</span>
                                    <x-status-badge :status="$record->status" />
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    <div class="mt-4"><x-progress label="Kehadiran semester" :value="$summaries[$child->id]['counts']['hadir'] + $summaries[$child->id]['counts']['terlambat']" :max="$summaries[$child->id]['total']" /></div>
                </x-card>
            @empty
                <x-empty-state icon="user-group" title="Belum ada anak yang tertaut" description="Minta admin kampus menautkan akunmu ke data anak." />
            @endforelse
        </section>
    @endif
</div>
