@php
    $levels = [
        'aman' => ['Aman', 'success', 'check-circle'],
        'waspada' => ['Waspada', 'warning', 'exclamation-triangle'],
        'risiko' => ['Tidak memenuhi', 'danger', 'x-circle'],
        'baru' => ['Belum ada pertemuan', 'neutral', 'clock'],
    ];
    $tones = ['success' => 'bg-success/15 text-success', 'warning' => 'bg-warning/15 text-warning', 'info' => 'bg-info/15 text-info', 'danger' => 'bg-danger/15 text-danger', 'neutral' => 'bg-surface-muted text-text-muted'];
    $letters = ['hadir' => 'H', 'terlambat' => 'T', 'izin' => 'I', 'sakit' => 'S', 'alpa' => 'A'];
@endphp
<div class="space-y-6">
    <x-page-header title="Presensi saya" :subtitle="$term ? 'Analitik kehadiran per mata kuliah · '.$term->name.' '.$term->semester : 'Analitik kehadiran per mata kuliah'" />
    <p class="-mt-3 text-sm text-text-muted">Presensi dicatat oleh dosen di kelas. Kamu tidak perlu mengisi sendiri; bila datamu keliru, sampaikan ke dosen pengampu.</p>

    @if (! $student)
        <x-card><x-empty-state icon="identification" title="Akunmu belum tertaut ke data mahasiswa" description="Minta BAAK menautkan akunmu agar presensi tampil di sini." /></x-card>
    @elseif (! $term)
        <x-card><x-empty-state icon="calendar-days" title="Belum ada semester aktif" description="Analitik tampil setelah BAAK mengaktifkan tahun ajaran." /></x-card>
    @elseif (! $group || $courses->isEmpty())
        <x-card><x-empty-state icon="user-group" title="Belum ada mata kuliah yang kamu ikuti" description="Kamu belum ditempatkan di kelas atau kelasmu belum punya mata kuliah semester ini. Hubungi BAAK bila ini keliru." /></x-card>
    @else
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <x-stat-tile label="Kehadiran keseluruhan" :value="$overall['percent'] === null ? '–' : $overall['percent'].'%'" :note="$overall['percent'] === null ? 'Belum ada pertemuan' : $overall['attended'].' dari '.$overall['held'].' pertemuan'"
                :tone="$overall['percent'] !== null && $overall['percent'] < $minPercent ? 'text-danger' : 'text-success'" />
            <x-stat-tile label="Mata kuliah diikuti" :value="$courses->count()" :note="$group->name" />
            <x-stat-tile label="Perlu diwaspadai" :value="$overall['atRisk']" :note="$overall['atRisk'] > 0 ? 'Jaga kehadiranmu' : 'Semua aman'" :tone="$overall['atRisk'] > 0 ? 'text-warning' : 'text-success'" />
            <x-stat-tile label="Total alpa" :value="$overall['absent']" :tone="$overall['absent'] > 0 ? 'text-danger' : 'text-text-muted'" />
        </div>

        @if ($todaySchedules->isNotEmpty())
            <x-card title="Hari ini" :padding="false">
                @foreach ($todaySchedules as $schedule)
                    @php $record = $todayRecords->get($schedule->id); @endphp
                    <div class="flex min-h-14 items-center gap-3 border-t border-border px-4 py-2.5 first:border-t-0" wire:key="today-{{ $schedule->id }}">
                        <span class="w-14 shrink-0 text-xs font-semibold tabular-nums text-text-muted">{{ substr($schedule->timeSlot->starts_at, 0, 5) }}<br>{{ substr($schedule->timeSlot->ends_at, 0, 5) }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">{{ $schedule->assignment->subject->name }}</p>
                            <p class="truncate text-xs text-text-muted">{{ $schedule->assignment->teacher->name }}@if ($schedule->room) · {{ $schedule->room->name }}@endif</p>
                        </div>
                        @if ($record)
                            <x-status-badge :status="$record->status" />
                        @else
                            <x-badge icon="clock">Belum dibuka</x-badge>
                        @endif
                    </div>
                @endforeach
            </x-card>
        @endif

        @php
            $slicesFor = fn (array $counts): array => collect($statuses)
                ->map(fn ($status): array => ['label' => $status->label(), 'value' => $counts[$status->value], 'variant' => $status->variant()])
                ->all();
            $overallCounts = collect($statuses)->mapWithKeys(fn ($status): array => [$status->value => $courses->sum(fn (array $course): int => $course['counts'][$status->value])])->all();
        @endphp

        <div class="grid gap-4 lg:grid-cols-5">
        <x-card title="Distribusi kehadiran" class="lg:col-span-2">
            <x-pie-chart :slices="$slicesFor($overallCounts)" size="size-36" title="Distribusi kehadiran semua mata kuliah" />
            <p class="mt-4 text-xs text-text-muted">Gabungan {{ $overall['held'] }} pertemuan dari {{ $courses->count() }} mata kuliah semester ini.</p>
        </x-card>

        <x-card :title="'Perbandingan kehadiran (batas '.$minPercent.'%)'" class="lg:col-span-3">
            <ul class="space-y-3" aria-label="Persentase kehadiran per mata kuliah">
                @foreach ($courses->sortBy(fn ($course) => $course['percent'] ?? 101) as $course)
                    <li wire:key="cmp-{{ $course['assignment']->id }}">
                        <div class="mb-1 flex items-center justify-between gap-3 text-xs">
                            <span class="min-w-0 truncate font-medium">{{ $course['subject']->name }}</span>
                            <span class="shrink-0 font-semibold tabular-nums">{{ $course['percent'] === null ? 'Belum ada data' : $course['percent'].'%' }}</span>
                        </div>
                        <div class="relative h-3 rounded-full bg-surface-muted" role="progressbar" aria-label="Kehadiran {{ $course['subject']->name }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $course['percent'] ?? 0 }}">
                            <div class="h-3 rounded-full {{ $course['percent'] !== null && $course['percent'] < $minPercent ? 'bg-danger' : 'bg-primary' }}" style="width: {{ $course['percent'] ?? 0 }}%" data-grow></div>
                            <span class="absolute -inset-y-1 w-0.5 rounded bg-text" style="left: {{ $minPercent }}%" aria-hidden="true"></span>
                        </div>
                    </li>
                @endforeach
            </ul>
            <p class="mt-4 flex items-center gap-2 text-xs text-text-muted"><span class="inline-block h-3 w-0.5 rounded bg-text" aria-hidden="true"></span> Garis penanda batas minimal {{ $minPercent }}% untuk mengikuti ujian.</p>
        </x-card>
        </div>

        <section aria-labelledby="per-matkul" class="space-y-4">
            <h2 id="per-matkul" class="text-lg font-semibold">Per mata kuliah</h2>

            <div class="grid gap-4 lg:grid-cols-2">
                @foreach ($courses as $course)
                    @php [$levelLabel, $levelVariant, $levelIcon] = $levels[$course['level']]; @endphp
                    <x-card wire:key="course-{{ $course['assignment']->id }}">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div class="min-w-0">
                                <h3 class="truncate text-base font-semibold">{{ $course['subject']->name }}</h3>
                                <p class="truncate text-xs text-text-muted">
                                    {{ $course['subject']->code }}@if ($course['subject']->credits) · {{ $course['subject']->credits }} SKS @endif· {{ $course['teacher']->name }}
                                </p>
                                @if ($course['slots']->isNotEmpty())
                                    <p class="mt-0.5 truncate text-xs text-text-muted">{{ $course['slots']->map(fn ($slot) => $slot->dayName().' '.substr($slot->starts_at, 0, 5))->implode(' · ') }}</p>
                                @endif
                            </div>
                            <x-badge :variant="$levelVariant" :icon="$levelIcon">{{ $levelLabel }}</x-badge>
                        </div>

                        <div class="mt-4 flex items-center gap-4">
                            <x-pie-chart :slices="$slicesFor($course['counts'])" size="size-20" :legend="false" :title="'Distribusi kehadiran '.$course['subject']->name" />
                            <div class="min-w-0 flex-1">
                                <p class="text-3xl font-bold leading-none tabular-nums">{{ $course['percent'] === null ? '–' : $course['percent'].'%' }}</p>
                                <p class="mt-1 text-xs text-text-muted tabular-nums">{{ $course['attended'] }} hadir dari {{ $course['total'] }} pertemuan · target {{ $course['planned'] }} per semester</p>
                            </div>
                        </div>

                        <dl class="mt-4 grid grid-cols-4 gap-2 text-center">
                            @foreach ($statuses as $status)
                                <div class="rounded-lg bg-surface-muted px-1 py-2">
                                    <dt class="text-[11px] font-medium text-text-muted">{{ $status->label() }}</dt>
                                    <dd class="text-lg font-bold tabular-nums">{{ $course['counts'][$status->value] }}</dd>
                                </div>
                            @endforeach
                        </dl>

                        @if ($course['total'] > 0)
                            <p class="mt-4 text-sm {{ $course['level'] === 'risiko' ? 'font-medium text-danger' : ($course['level'] === 'waspada' ? 'font-medium text-warning' : 'text-text-muted') }}" role="status">
                                @if ($course['level'] === 'risiko')
                                    Ketidakhadiranmu ({{ $course['missed'] }}) sudah melebihi batas {{ $course['allowed'] }} pertemuan, sehingga {{ $minPercent }}% tidak lagi tercapai. Segera temui dosen wali atau BAAK.
                                @elseif ($course['level'] === 'waspada')
                                    Sisa toleransi tidak hadir tinggal {{ $course['remaining'] }} dari {{ $course['allowed'] }} pertemuan. Usahakan hadir di pertemuan berikutnya.
                                @else
                                    Sisa toleransi tidak hadir: {{ $course['remaining'] }} dari {{ $course['allowed'] }} pertemuan.
                                @endif
                            </p>

                            <div class="mt-4">
                                <p class="mb-1.5 text-xs font-medium text-text-muted">Riwayat pertemuan</p>
                                <ol class="flex flex-wrap gap-1" aria-label="Riwayat kehadiran {{ $course['subject']->name }}">
                                    @foreach ($course['history'] as $item)
                                        <li class="flex size-8 items-center justify-center rounded-md text-xs font-bold {{ $tones[$item['status']->variant()] }}"
                                            title="{{ $item['date']->translatedFormat('j M Y') }} · {{ $item['status']->label() }}">
                                            <span aria-hidden="true">{{ $letters[$item['status']->value] }}</span>
                                            <span class="sr-only">{{ $item['date']->translatedFormat('j M Y') }}: {{ $item['status']->label() }}</span>
                                        </li>
                                    @endforeach
                                </ol>
                            </div>

                            <details class="group mt-3">
                                <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-2 text-sm font-medium text-primary [&::-webkit-details-marker]:hidden">
                                    Lihat rincian pertemuan
                                    <x-icon name="chevron-down" class="size-4 transition group-open:rotate-180" />
                                </summary>
                                <ul class="divide-y divide-border">
                                    @foreach ($course['history']->reverse() as $item)
                                        <li class="flex min-h-11 items-center justify-between gap-3 py-2 text-sm">
                                            <span class="tabular-nums">{{ $item['date']->translatedFormat('l, j M Y') }}</span>
                                            <x-status-badge :status="$item['status']" />
                                        </li>
                                    @endforeach
                                </ul>
                            </details>
                        @else
                            <p class="mt-4 text-sm text-text-muted">Belum ada pertemuan tercatat semester ini.</p>
                        @endif
                    </x-card>
                @endforeach
            </div>
        </section>

        <p class="text-xs text-text-muted">Persentase dihitung dari pertemuan yang sudah berlangsung; Izin dan Sakit tidak dihitung hadir. Status memperhitungkan {{ $planned }} pertemuan per semester dan batas minimal {{ $minPercent }}%.</p>
    @endif
</div>
