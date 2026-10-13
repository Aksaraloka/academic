<div class="space-y-6">
    <x-page-header title="Jadwal" subtitle="Jadwal pelajaran per rombel, guru, atau ruang.">
        @if (auth()->user()->hasRole(\App\Enums\Role::Admin, \App\Enums\Role::Curriculum))
            <x-slot:actions>
                <x-button :href="route('schedule.manage')" wire:navigate icon="pencil-square">Atur jadwal</x-button>
            </x-slot:actions>
        @endif
    </x-page-header>

    @if ($canPickTarget)
        <div class="grid gap-3 sm:grid-cols-2">
            <div>
                <label for="lihat" class="sr-only">Tampilkan menurut</label>
                <select id="lihat" wire:model.live="view" class="input">
                    <option value="group">Per rombel</option>
                    <option value="teacher">Per guru</option>
                    <option value="room">Per ruang</option>
                </select>
            </div>
            <div>
                <label for="target" class="sr-only">Pilihan</label>
                <select id="target" wire:model.live="targetId" class="input">
                    <option value="">Pilih…</option>
                    @foreach ($options as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    @endif

    @if ($schedulesByDay->isEmpty())
        <x-card><x-empty-state icon="calendar-days" title="Belum ada jadwal" description="Jadwal akan tampil di sini setelah diterbitkan oleh wakasek kurikulum atau admin." /></x-card>
    @else
        <div class="grid gap-4 lg:grid-cols-2">
            @foreach ($days as $dayNumber => $dayName)
                @continue(! $schedulesByDay->has($dayNumber))
                <x-card :title="$dayName" :padding="false" wire:key="day-{{ $dayNumber }}">
                    @foreach ($schedulesByDay[$dayNumber] as $schedule)
                        <div class="flex min-h-14 items-center gap-3 border-t border-border px-4 py-2.5 first:border-t-0">
                            <span class="w-14 shrink-0 text-xs font-semibold tabular-nums text-text-muted">{{ substr($schedule->timeSlot->starts_at, 0, 5) }}<br>{{ substr($schedule->timeSlot->ends_at, 0, 5) }}</span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium">{{ $schedule->assignment->subject->name }}</p>
                                <p class="truncate text-xs text-text-muted">
                                    @if ($view !== 'group'){{ $schedule->assignment->studyGroup->name }} · @endif
                                    @if ($view !== 'teacher'){{ $schedule->assignment->teacher->name }}@endif
                                    @if ($schedule->room) · {{ $schedule->room->name }}@endif
                                </p>
                            </div>
                        </div>
                    @endforeach
                </x-card>
            @endforeach
        </div>
    @endif
</div>
