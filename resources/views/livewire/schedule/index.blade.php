@php
    // Warna per mata kuliah: garis kiri dan latar tipis dari token tema (tanpa hex).
    $palette = [
        ['border-primary', 'bg-primary/10', 'bg-primary'],
        ['border-info', 'bg-info/10', 'bg-info'],
        ['border-success', 'bg-success/10', 'bg-success'],
        ['border-warning', 'bg-warning/10', 'bg-warning'],
        ['border-text-muted', 'bg-surface-muted', 'bg-text-muted'],
    ];
    $tone = fn (int $subjectId): array => $palette[$subjectId % count($palette)];
    $hm = fn (string $time): string => substr($time, 0, 5);
    $duration = fn (int $minutes): string => intdiv($minutes, 60).' jam'.($minutes % 60 ? ' '.($minutes % 60).' menit' : '');
    $isNow = fn ($slot): bool => $termIsActive && $slot->day === $todayDay && $nowTime >= $slot->starts_at && $nowTime <= $slot->ends_at;
    $detail = function ($schedule) use ($view): string {
        $assignment = $schedule->assignment;

        return match ($view) {
            'teacher' => $assignment->studyGroup->name,
            'room' => $assignment->studyGroup->name.' · '.$assignment->teacher->name,
            default => $assignment->teacher->name,
        };
    };
@endphp
<div class="space-y-6">
    <x-page-header title="Jadwal" :subtitle="$term ? 'Jadwal kuliah mingguan · '.$term->name.' semester '.$term->semester : 'Jadwal kuliah mingguan'">
        @if (($canManage && $termIsActive) || $stats['meetings'] > 0)
            <x-slot:actions>
                @if ($stats['meetings'] > 0)
                    <x-button :href="route('schedule.export', $exportQuery)" variant="secondary" icon="arrow-down-tray" download>Unduh PDF</x-button>
                @endif
                @if ($canManage && $termIsActive)
                    <x-button :href="route('schedule.manage')" wire:navigate icon="pencil-square">Atur jadwal</x-button>
                @endif
            </x-slot:actions>
        @endif
    </x-page-header>

    <div class="grid gap-3 sm:grid-cols-2 {{ $canPickTarget ? 'lg:grid-cols-4' : 'lg:grid-cols-3' }}">
        <div>
            <label for="semester" class="mb-1 block text-xs font-medium text-text-muted">Semester</label>
            <select id="semester" wire:model.live="termId" class="input">
                @foreach ($terms as $option)
                    <option value="{{ $option->id }}">{{ $option->name }} · {{ $option->semester }}{{ $option->is_active ? ' (aktif)' : '' }}</option>
                @endforeach
            </select>
        </div>
        @if ($canPickTarget)
            <div>
                <label for="lihat" class="mb-1 block text-xs font-medium text-text-muted">Tampilkan menurut</label>
                <select id="lihat" wire:model.live="view" class="input">
                    <option value="group">Kelas</option>
                    <option value="teacher">Dosen</option>
                    <option value="room">Ruang</option>
                </select>
            </div>
            <div>
                <label for="target" class="mb-1 block text-xs font-medium text-text-muted">{{ ['group' => 'Kelas', 'teacher' => 'Dosen', 'room' => 'Ruang'][$view] }}</label>
                <select id="target" wire:model.live="targetId" class="input">
                    <option value="">Pilih…</option>
                    @foreach ($options as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                </select>
            </div>
        @endif
    </div>

    @if ($term && ! $termIsActive)
        <p class="flex items-start gap-2 rounded-lg border border-border bg-surface-muted p-3 text-sm text-text-muted" role="status">
            <x-icon name="information-circle" class="mt-0.5 size-4 shrink-0" />
            <span>Kamu melihat arsip jadwal semester {{ $term->name }} {{ $term->semester }} ({{ $term->starts_at->translatedFormat('j M Y') }} – {{ $term->ends_at->translatedFormat('j M Y') }}). Jadwal ini hanya untuk dibaca.</span>
        </p>
    @endif

    @if (! $term)
        <x-card><x-empty-state icon="calendar-days" title="Belum ada semester" description="Jadwal tampil setelah tahun ajaran dibuat oleh BAAK." /></x-card>
    @elseif ($stats['meetings'] === 0)
        <x-card>
            <x-empty-state icon="calendar-days"
                :title="$targetId === '' ? 'Pilih dulu yang ingin dilihat' : 'Belum ada jadwal'"
                :description="$targetId === '' ? 'Pilih kelas, dosen, atau ruang untuk menampilkan jadwalnya.' : ($termIsActive ? 'Jadwal akan tampil di sini setelah diterbitkan oleh wakil dekan akademik atau admin.' : 'Tidak ada jadwal yang tercatat pada semester ini.')" />
        </x-card>
    @else
        <div class="grid grid-cols-3 gap-3">
            <x-stat-tile label="Pertemuan / minggu" :value="$stats['meetings']" />
            <x-stat-tile label="Mata kuliah" :value="$stats['courses']" />
            <x-stat-tile label="Total tatap muka" :value="$duration($stats['minutes'])" />
        </div>

        {{-- Tampilan mingguan (layar lebar) --}}
        <section class="hidden lg:block" aria-labelledby="mingguan">
            <h2 id="mingguan" class="sr-only">Jadwal mingguan</h2>
            <div class="overflow-x-auto rounded-card border border-border bg-surface">
                <div class="grid min-w-[56rem]" style="grid-template-columns: 7rem repeat({{ $days->count() }}, minmax(0, 1fr));">
                    <div class="border-b border-border bg-surface-muted px-3 py-3 text-xs font-semibold uppercase tracking-wide text-text-muted">Sesi</div>
                    @foreach ($days as $dayNumber => $dayName)
                        <div class="border-b border-l border-border px-3 py-3 text-center text-sm font-semibold {{ $termIsActive && $dayNumber === $todayDay ? 'bg-primary/15 text-primary' : 'bg-surface-muted' }}">
                            {{ $dayName }}
                            @if ($termIsActive && $dayNumber === $todayDay)<span class="block text-[11px] font-medium">Hari ini</span>@endif
                        </div>
                    @endforeach

                    @foreach ($periods as $periodNumber => $period)
                        <div class="flex flex-col justify-center border-t border-border bg-surface-muted px-3 py-3">
                            <span class="text-sm font-semibold">Sesi {{ $periodNumber }}</span>
                            <span class="text-xs tabular-nums text-text-muted">{{ $hm($period->starts_at) }}–{{ $hm($period->ends_at) }}</span>
                        </div>
                        @foreach ($days as $dayNumber => $dayName)
                            @php $cell = $grid->get($periodNumber.'-'.$dayNumber, collect()); @endphp
                            <div class="min-h-24 space-y-2 border-l border-t border-border p-2 {{ $termIsActive && $dayNumber === $todayDay ? 'bg-primary/5' : '' }}" wire:key="cell-{{ $periodNumber }}-{{ $dayNumber }}">
                                @foreach ($cell as $schedule)
                                    @php
                                        [$border, $background] = $tone($schedule->assignment->subject_id);
                                        $now = $isNow($schedule->timeSlot);
                                    @endphp
                                    <div class="rounded-lg border-l-4 {{ $border }} {{ $background }} p-2.5 {{ $now ? 'ring-2 ring-primary' : '' }}">
                                        <p class="text-sm font-semibold leading-snug">{{ $schedule->assignment->subject->name }}</p>
                                        <p class="mt-0.5 truncate text-xs text-text-muted">{{ $detail($schedule) }}</p>
                                        <div class="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-text-muted">
                                            @if ($schedule->room)<span class="inline-flex items-center gap-1"><x-icon name="map-pin" solid /> {{ $schedule->room->name }}</span>@endif
                                            @if ($now)<x-badge variant="primary" class="!px-2 !py-0">Berlangsung</x-badge>@endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    @endforeach
                </div>
            </div>
        </section>

        {{-- Tampilan per hari (HP dan tablet) --}}
        <section class="lg:hidden" aria-labelledby="harian" x-data="{ day: {{ $initialDay }} }">
            <h2 id="harian" class="sr-only">Jadwal per hari</h2>
            <div role="tablist" aria-label="Pilih hari" class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-2 sm:mx-0 sm:px-0">
                @foreach ($days as $dayNumber => $dayName)
                    <button type="button" role="tab" id="tab-{{ $dayNumber }}" aria-controls="panel-{{ $dayNumber }}" :aria-selected="day === {{ $dayNumber }}" @click="day = {{ $dayNumber }}"
                        class="inline-flex min-h-11 shrink-0 flex-col items-center justify-center rounded-lg border px-4 text-sm font-semibold transition"
                        :class="day === {{ $dayNumber }} ? 'border-primary bg-primary/15 text-primary' : 'border-border bg-surface text-text-muted hover:bg-surface-muted'">
                        {{ $dayName }}
                        <span class="text-[10px] font-medium">{{ ($byDay->get($dayNumber)?->count() ?? 0) }} sesi{{ $termIsActive && $dayNumber === $todayDay ? ' · hari ini' : '' }}</span>
                    </button>
                @endforeach
            </div>

            @foreach ($days as $dayNumber => $dayName)
                <div role="tabpanel" id="panel-{{ $dayNumber }}" aria-labelledby="tab-{{ $dayNumber }}" x-show="day === {{ $dayNumber }}" @if ($dayNumber !== $initialDay) x-cloak @endif class="mt-2 space-y-3" wire:key="day-{{ $dayNumber }}">
                    @forelse ($byDay->get($dayNumber, collect()) as $schedule)
                        @php
                            [$border, $background] = $tone($schedule->assignment->subject_id);
                            $now = $isNow($schedule->timeSlot);
                        @endphp
                        <div class="flex gap-3 rounded-card border border-border bg-surface p-3 {{ $now ? 'ring-2 ring-primary' : '' }}">
                            <div class="w-14 shrink-0 text-center">
                                <p class="text-sm font-bold tabular-nums">{{ $hm($schedule->timeSlot->starts_at) }}</p>
                                <p class="text-xs tabular-nums text-text-muted">{{ $hm($schedule->timeSlot->ends_at) }}</p>
                            </div>
                            <div class="min-w-0 flex-1 rounded-lg border-l-4 {{ $border }} {{ $background }} px-3 py-2">
                                <p class="text-sm font-semibold leading-snug">{{ $schedule->assignment->subject->name }}</p>
                                <p class="mt-0.5 truncate text-xs text-text-muted">{{ $detail($schedule) }}</p>
                                <div class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-text-muted">
                                    <span>Sesi {{ $schedule->timeSlot->period_number }}</span>
                                    @if ($schedule->room)<span class="inline-flex items-center gap-1"><x-icon name="map-pin" solid /> {{ $schedule->room->name }}</span>@endif
                                    @if ($now)<x-badge variant="primary" class="!px-2 !py-0">Berlangsung</x-badge>@endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <x-card><x-empty-state icon="calendar-days" title="Tidak ada kuliah" description="Tidak ada jadwal pada hari {{ $dayName }}." /></x-card>
                    @endforelse
                </div>
            @endforeach
        </section>

        <x-card title="Mata kuliah pada jadwal ini" :padding="false">
            <ul class="grid divide-y divide-border sm:grid-cols-2 sm:divide-y-0 lg:grid-cols-3">
                @foreach ($subjects as $assignment)
                    @php [, , $dot] = $tone($assignment->subject_id); @endphp
                    <li class="flex min-h-14 items-center gap-3 px-4 py-2.5 sm:border-b sm:border-border" wire:key="legend-{{ $assignment->subject_id }}">
                        <span class="size-3 shrink-0 rounded-sm {{ $dot }}" aria-hidden="true"></span>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium">{{ $assignment->subject->name }}</p>
                            <p class="truncate text-xs text-text-muted">
                                {{ $assignment->subject->code }}@if ($assignment->subject->credits) · {{ $assignment->subject->credits }} SKS @endif
                                @if ($view !== 'teacher') · {{ $assignment->teacher->name }}@endif
                            </p>
                        </div>
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endif
</div>
