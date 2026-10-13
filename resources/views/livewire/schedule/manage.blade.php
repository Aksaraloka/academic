<div class="space-y-6">
    <x-page-header title="Atur jadwal" subtitle="Sistem memeriksa bentrok guru, rombel, dan ruang sebelum jadwal disimpan.">
        <x-slot:actions>
            <x-button :href="route('schedule.index')" wire:navigate variant="secondary" icon="arrow-left">Kembali</x-button>
        </x-slot:actions>
    </x-page-header>

    @if ($timeSlots->isEmpty())
        <x-card>
            <x-empty-state icon="clock" title="Jam pelajaran belum diatur" description="Buat jam pelajaran standar (jam ke-1 sampai ke-6, Senin–Jumat) lalu sesuaikan nanti.">
                <x-button wire:click="seedTimeSlots" icon="plus">Buat jam standar</x-button>
            </x-empty-state>
        </x-card>
    @else
        <form wire:submit="place">
            <x-card title="Tambah jadwal">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field label="Rombel" name="groupId" required>
                        <select id="groupId" wire:model.live="groupId" class="input">
                            <option value="">Pilih rombel</option>
                            @foreach ($groups as $group)<option value="{{ $group->id }}">{{ $group->name }}</option>@endforeach
                        </select>
                    </x-field>
                    <x-field label="Guru" name="teacherId" required>
                        <select id="teacherId" wire:model="teacherId" class="input">
                            <option value="">Pilih guru</option>
                            @foreach ($teachers as $teacher)<option value="{{ $teacher->id }}">{{ $teacher->name }}</option>@endforeach
                        </select>
                    </x-field>
                    <x-field label="Mata pelajaran" name="subjectId" required>
                        <select id="subjectId" wire:model="subjectId" class="input">
                            <option value="">Pilih mapel</option>
                            @foreach ($subjects as $subject)<option value="{{ $subject->id }}">{{ $subject->name }}</option>@endforeach
                        </select>
                    </x-field>
                    <x-field label="Jam pelajaran" name="timeSlotId" required>
                        <select id="timeSlotId" wire:model="timeSlotId" class="input">
                            <option value="">Pilih hari dan jam</option>
                            @foreach ($timeSlots as $slot)<option value="{{ $slot->id }}">{{ $slot->dayName() }} · jam ke-{{ $slot->period_number }} ({{ $slot->timeRange() }})</option>@endforeach
                        </select>
                    </x-field>
                    <x-field label="Ruang" name="roomId">
                        <select id="roomId" wire:model="roomId" class="input">
                            <option value="">Tanpa ruang khusus</option>
                            @foreach ($rooms as $room)<option value="{{ $room->id }}">{{ $room->name }}</option>@endforeach
                        </select>
                    </x-field>
                </div>

                @if ($conflicts)
                    <div class="mt-4 rounded-lg border border-danger bg-danger/15 p-3 text-sm text-danger" role="alert">
                        <p class="flex items-center gap-2 font-semibold"><x-icon name="exclamation-triangle" class="size-4" /> Jadwal bentrok</p>
                        <ul class="mt-1 list-inside list-disc">
                            @foreach ($conflicts as $conflict)<li>{{ $conflict }}</li>@endforeach
                        </ul>
                    </div>
                @endif

                <div class="mt-4"><x-button type="submit" wire:loading.attr="disabled" wire:target="place">Simpan perubahan</x-button></div>
            </x-card>
        </form>

        <div class="grid gap-4 sm:grid-cols-2">
            <form wire:submit="addSubject">
                <x-card title="Mata pelajaran baru">
                    <x-field label="Nama" name="newSubject"><input id="newSubject" type="text" wire:model="newSubject" class="input"></x-field>
                    <div class="mt-3"><x-button type="submit" variant="secondary" size="sm" icon="plus">Tambah</x-button></div>
                </x-card>
            </form>
            <form wire:submit="addRoom">
                <x-card title="Ruang baru">
                    <x-field label="Nama" name="newRoom"><input id="newRoom" type="text" wire:model="newRoom" class="input"></x-field>
                    <div class="mt-3"><x-button type="submit" variant="secondary" size="sm" icon="plus">Tambah</x-button></div>
                </x-card>
            </form>
        </div>

        @if ($groupId !== '')
            <x-card title="Jadwal rombel terpilih" :padding="false">
                @forelse ($schedules as $schedule)
                    <div class="flex min-h-14 items-center gap-3 border-t border-border px-4 py-2.5 first:border-t-0" wire:key="s-{{ $schedule->id }}">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">{{ $schedule->assignment->subject->name }} · {{ $schedule->assignment->teacher->name }}</p>
                            <p class="truncate text-xs text-text-muted">{{ $schedule->timeSlot->dayName() }} · jam ke-{{ $schedule->timeSlot->period_number }} · {{ $schedule->timeSlot->timeRange() }}@if ($schedule->room) · {{ $schedule->room->name }}@endif</p>
                        </div>
                        <x-button variant="ghost" size="sm" wire:click="remove({{ $schedule->id }})" wire:confirm="Hapus jadwal ini?">
                            <x-icon name="trash" class="size-4 text-danger" /><span class="sr-only">Hapus jadwal</span>
                        </x-button>
                    </div>
                @empty
                    <x-empty-state icon="calendar-days" title="Rombel ini belum punya jadwal" description="Gunakan formulir di atas untuk menambahkan pelajaran pertama." />
                @endforelse
            </x-card>
        @endif
    @endif
</div>
