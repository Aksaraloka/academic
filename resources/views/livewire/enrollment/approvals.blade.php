<div class="space-y-6">
    <x-page-header title="Persetujuan KRS" :subtitle="$isTeacher ?? false ? 'KRS mahasiswa perwalianmu menunggu persetujuan.' : 'Pantau dan setujui KRS mahasiswa per semester.'" />

    @if (! $term)
        <x-card><x-empty-state icon="calendar-days" title="Belum ada semester" description="Admin perlu menambahkan semester akademik lebih dulu." /></x-card>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-field label="Semester" name="termId">
                <select id="termId" wire:model.live="termId" class="input">
                    @foreach ($terms as $option)
                        <option value="{{ $option->id }}" @selected($option->id === $term->id)>{{ $option->name }} · {{ $option->semester }}{{ $option->is_active ? ' (aktif)' : '' }}</option>
                    @endforeach
                </select>
            </x-field>
            <x-field label="Status" name="status">
                <select id="status" wire:model.live="status" class="input">
                    <option value="">Semua status</option>
                    @foreach ($statuses as $option)<option value="{{ $option->value }}">{{ $option->label() }} ({{ $counts[$option->value] ?? 0 }})</option>@endforeach
                </select>
            </x-field>
        </div>

        <p class="text-sm text-text-muted">{{ $notStarted }} mahasiswa belum memulai KRS semester ini.</p>

        @error('decision')<p class="rounded-lg border border-danger/40 bg-danger/10 p-3 text-sm text-danger" role="alert">{{ $message }}</p>@enderror

        <x-card :padding="false">
            @forelse ($enrollments as $enrollment)
                <div class="border-t border-border px-4 py-3 first:border-t-0" wire:key="enr-{{ $enrollment->id }}">
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">{{ $enrollment->student->name }}</p>
                            <p class="truncate text-xs text-text-muted">{{ $enrollment->student->nim }} · {{ $enrollment->assignments->count() }} mata kuliah · {{ $enrollment->credits() }} SKS</p>
                        </div>
                        <x-badge :variant="$enrollment->status->variant()">{{ $enrollment->status->label() }}</x-badge>
                        <x-button size="sm" variant="ghost" :href="route('advising.index', ['student' => $enrollment->student_id])" wire:navigate>Bimbingan</x-button>
                        @if ($enrollment->status === \App\Enums\EnrollmentStatus::Submitted)
                            <div class="flex gap-2">
                                <x-button size="sm" wire:click="approve({{ $enrollment->id }})" wire:confirm="Setujui KRS {{ $enrollment->student->name }}?">Setujui</x-button>
                                <x-button size="sm" variant="secondary" wire:click="startReject({{ $enrollment->id }})">Tolak</x-button>
                            </div>
                        @endif
                    </div>

                    <details class="mt-2">
                        <summary class="min-h-11 cursor-pointer py-2 text-sm font-medium text-primary">Lihat mata kuliah</summary>
                        <ul class="space-y-1 pb-2 text-sm">
                            @foreach ($enrollment->assignments as $assignment)
                                <li class="flex justify-between gap-3"><span>{{ $assignment->subject->code }} · {{ $assignment->subject->name }} <span class="text-text-muted">({{ $assignment->classLabel() }})</span></span><span class="text-text-muted">{{ $assignment->subject->credits }} SKS</span></li>
                            @endforeach
                        </ul>
                        @if ($enrollment->advisor_note)<p class="pb-2 text-xs text-text-muted">Catatan: {{ $enrollment->advisor_note }}</p>@endif
                    </details>

                    @if ($rejectingId === $enrollment->id)
                        <form wire:submit="reject" class="mt-2 space-y-2">
                            <x-field label="Alasan penolakan" name="rejectNote" required>
                                <input id="rejectNote" type="text" wire:model="rejectNote" class="input" placeholder="Mis. kurangi SKS atau pilih kelas lain" @error('rejectNote') aria-invalid="true" @enderror>
                            </x-field>
                            <div class="flex gap-2">
                                <x-button type="submit" size="sm">Kirim penolakan</x-button>
                                <x-button type="button" size="sm" variant="secondary" wire:click="cancelReject">Batal</x-button>
                            </div>
                        </form>
                    @endif
                </div>
            @empty
                <x-empty-state icon="clipboard-document-list" title="Tidak ada KRS" description="Belum ada KRS dengan status ini pada semester yang dipilih." />
            @endforelse
        </x-card>
    @endif
</div>
