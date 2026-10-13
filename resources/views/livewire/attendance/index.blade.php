<div class="space-y-6">
    <x-page-header title="Presensi" :subtitle="now()->translatedFormat('l, j F Y')" />

    @if ($tenant->usesPerHourAttendance())
        <x-card title="Per jam pelajaran" :padding="false">
            @forelse ($schedules as $schedule)
                @php
                    $session = $sessionsBySchedule->get($schedule->id);
                    $isNow = now()->format('H:i:s') >= $schedule->timeSlot->starts_at && now()->format('H:i:s') <= $schedule->timeSlot->ends_at;
                @endphp
                <div class="flex min-h-14 flex-wrap items-center gap-3 border-t border-border px-4 py-2.5 first:border-t-0 hover:bg-surface-muted" wire:key="sch-{{ $schedule->id }}">
                    <x-icon name="clipboard-document-list" class="text-primary" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium">{{ $schedule->assignment->studyGroup->name }} · {{ $schedule->assignment->subject->name }}</p>
                        <p class="truncate text-xs {{ $isNow ? 'font-semibold text-warning' : 'text-text-muted' }}">
                            Jam ke-{{ $schedule->timeSlot->period_number }} · {{ $schedule->timeSlot->timeRange() }} · {{ $schedule->assignment->teacher->name }}
                        </p>
                    </div>
                    @if ($session)
                        <x-badge variant="success" icon="check-circle">{{ $session->present_count }}/{{ $session->records_count }} hadir</x-badge>
                        <x-button :href="route('attendance.mark', $session)" wire:navigate size="sm" variant="secondary">Buka</x-button>
                    @elseif ($canOpen && (auth()->user()->isAdmin() || $schedule->assignment->teacher_id === auth()->user()->teacher?->id))
                        <div class="flex gap-2">
                            <x-button size="sm" wire:click="open({{ $schedule->id }}, 'manual')" wire:loading.attr="disabled" wire:target="open">Presensi</x-button>
                            <x-button size="sm" variant="secondary" icon="qr-code" wire:click="open({{ $schedule->id }}, 'qr')" wire:loading.attr="disabled" wire:target="open">QR</x-button>
                        </div>
                    @else
                        <x-badge variant="warning" icon="clock">Belum diisi</x-badge>
                    @endif
                </div>
            @empty
                <x-empty-state icon="calendar-days" title="Tidak ada jam pelajaran hari ini" description="Jadwal yang jatuh hari ini akan muncul di sini." />
            @endforelse
        </x-card>
    @endif

    @if ($tenant->usesDailyAttendance())
        <x-card title="Presensi harian" :padding="false">
            @forelse ($dailyGroups as $group)
                @php $session = $dailySessions->get($group->id); @endphp
                <div class="flex min-h-14 items-center gap-3 border-t border-border px-4 py-2.5 first:border-t-0 hover:bg-surface-muted" wire:key="daily-{{ $group->id }}">
                    <x-icon name="user-group" class="text-primary" />
                    <span class="min-w-0 flex-1 truncate text-sm font-medium">{{ $group->name }}</span>
                    @if ($session)
                        <x-button :href="route('attendance.mark', $session)" wire:navigate size="sm" variant="secondary">Buka</x-button>
                    @elseif ($canOpen)
                        <x-button size="sm" wire:click="openDaily({{ $group->id }})" wire:loading.attr="disabled" wire:target="openDaily">Mulai presensi</x-button>
                    @else
                        <x-badge variant="warning" icon="clock">Belum diisi</x-badge>
                    @endif
                </div>
            @empty
                <x-empty-state icon="user-group" title="Tidak ada kelas untuk diisi" description="Presensi harian dilakukan oleh wali kelas." />
            @endforelse
        </x-card>
    @endif
</div>
