<div class="space-y-6">
    <x-page-header
        :title="$session->studyGroup->name"
        :subtitle="($session->schedule ? $session->schedule->assignment->subject->name.' · sesi ke-'.$session->schedule->timeSlot->period_number.' · ' : 'Presensi harian · ').$session->date->translatedFormat('j F Y')">
        <x-slot:actions>
            <x-button :href="route('attendance.index')" wire:navigate variant="secondary" icon="arrow-left">Kembali</x-button>
            @if ($session->mode === \App\Enums\AttendanceMode::Qr && $canEdit)
                <x-button :href="route('attendance.qr', $session)" wire:navigate variant="secondary" icon="qr-code">Tampilkan QR</x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="flex flex-wrap gap-2" role="status" aria-live="polite">
        @foreach ($levelStatuses as $status)
            <x-status-badge :status="$status" /><span class="mr-2 text-sm font-semibold tabular-nums">{{ $counts[$status->value] ?? 0 }}</span>
        @endforeach
    </div>

    @unless ($canEdit)
        <p class="rounded-lg border border-border bg-surface-muted p-3 text-sm text-text-muted" role="status">Kamu hanya bisa melihat presensi ini. Koreksi setelah hari berlalu dilakukan oleh dosen wali atau admin.</p>
    @endunless

    <x-card :padding="false">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-border px-4 py-3">
            <p class="text-sm text-text-muted">Ketuk nama untuk menandai tidak hadir.</p>
            @if ($canEdit)
                <x-button size="sm" variant="secondary" wire:click="markAllPresent" icon="check-circle">Semua hadir</x-button>
            @endif
        </div>

        @forelse ($students as $student)
            @php $current = \App\Enums\AttendanceStatus::from($statuses[$student->id] ?? 'hadir'); @endphp
            <div class="flex min-h-14 items-center gap-2 border-t border-border px-4 py-2 first:border-t-0" wire:key="stu-{{ $student->id }}">
                <button type="button" @disabled(! $canEdit) wire:click="toggleAbsent({{ $student->id }})" class="flex min-h-11 min-w-0 flex-1 items-center gap-3 rounded-lg text-left hover:bg-surface-muted">
                    <span class="min-w-0 flex-1 truncate text-sm font-medium">{{ $student->name }}</span>
                    <x-status-badge :status="$current" />
                </button>
                <label class="sr-only" for="st-{{ $student->id }}">Status {{ $student->name }}</label>
                <select id="st-{{ $student->id }}" wire:model.live="statuses.{{ $student->id }}" @disabled(! $canEdit) class="input w-28 shrink-0 !min-h-11 !px-2 !text-sm">
                    @foreach ($levelStatuses as $option)
                        <option value="{{ $option->value }}">{{ $option->label() }}</option>
                    @endforeach
                </select>
            </div>
        @empty
            <x-empty-state icon="users" title="Kelas ini belum punya mahasiswa" description="Tempatkan mahasiswa di halaman Kelas terlebih dahulu." />
        @endforelse
    </x-card>

    @if ($canEdit && $students->isNotEmpty())
        <div class="flex flex-wrap gap-2">
            <x-button wire:click="finish" wire:loading.attr="disabled" wire:target="finish,save" icon="check">Simpan dan selesai</x-button>
            <x-button variant="secondary" wire:click="save" wire:loading.attr="disabled" wire:target="save,finish">Simpan sementara</x-button>
        </div>
    @endif
</div>
