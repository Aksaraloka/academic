<div class="space-y-6">
    <x-page-header title="Jadwal ujian" subtitle="Jadwal UTS dan UAS per mata kuliah. Kartu ujian terbit bagi mahasiswa yang memenuhi syarat kehadiran.">
        <x-slot:actions>
            <x-button :href="route('academic.calendar')" wire:navigate variant="secondary" icon="calendar-days">Kalender akademik</x-button>
        </x-slot:actions>
    </x-page-header>

    @if (! $term)
        <x-card><x-empty-state icon="calendar-days" title="Belum ada semester" description="Admin perlu menambahkan semester akademik lebih dulu." /></x-card>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-field label="Semester" name="termId">
                <select id="termId" wire:model.live="termId" class="input">
                    @foreach ($terms as $option)<option value="{{ $option->id }}" @selected($option->id === $term->id)>{{ $option->name }} · {{ $option->semester }}{{ $option->is_active ? ' (aktif)' : '' }}</option>@endforeach
                </select>
            </x-field>
            <x-field label="Jenis ujian" name="typeFilter">
                <select id="typeFilter" wire:model.live="typeFilter" class="input">
                    <option value="">UTS dan UAS</option>
                    @foreach ($types as $option)<option value="{{ $option->value }}">{{ $option->label() }}</option>@endforeach
                </select>
            </x-field>
        </div>

        @if ($canManage)
            <form wire:submit="save">
                <x-card :title="$editingId ? 'Ubah jadwal ujian' : 'Jadwal ujian baru'">
                    <p class="mb-3 text-sm text-text-muted">Satu jadwal berlaku untuk semua kelas mata kuliah yang dipilih. Tanggal harus dalam masa UTS atau UAS di Kalender akademik, dan tidak boleh bentrok untuk kelas atau ruang yang sama.</p>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <x-field label="Jenis" name="type" required>
                            <select id="type" wire:model="type" class="input">@foreach ($types as $option)<option value="{{ $option->value }}">{{ $option->label() }}</option>@endforeach</select>
                        </x-field>
                        <div class="sm:col-span-2 lg:col-span-3">
                            <x-field label="Mata kuliah" name="subjectId" required>
                                <select id="subjectId" wire:model="subjectId" class="input" @disabled($editingId)>
                                    <option value="">Pilih mata kuliah</option>
                                    @foreach ($subjects as $subject)<option value="{{ $subject->id }}">{{ $subject->code }} · {{ $subject->name }}</option>@endforeach
                                </select>
                            </x-field>
                        </div>
                        <x-field label="Tanggal" name="date" required><input id="date" type="date" wire:model="date" class="input" @error('date') aria-invalid="true" @enderror></x-field>
                        <x-field label="Mulai" name="starts_at" required><input id="starts_at" type="time" wire:model="starts_at" class="input"></x-field>
                        <x-field label="Selesai" name="ends_at" required><input id="ends_at" type="time" wire:model="ends_at" class="input"></x-field>
                        <x-field label="Ruang" name="roomId">
                            <select id="roomId" wire:model="roomId" class="input"><option value="">Belum ditentukan</option>@foreach ($rooms as $room)<option value="{{ $room->id }}">{{ $room->name }}</option>@endforeach</select>
                        </x-field>
                        <div class="sm:col-span-2 lg:col-span-4"><x-field label="Catatan" name="notes"><input id="notes" type="text" wire:model="notes" class="input" placeholder="Mis. open book, bawa kalkulator"></x-field></div>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <x-button type="submit" wire:loading.attr="disabled" wire:target="save">{{ $editingId ? 'Simpan perubahan' : 'Tambah jadwal' }}</x-button>
                        @if ($editingId)<x-button type="button" variant="secondary" wire:click="cancel">Batal</x-button>@endif
                    </div>
                </x-card>
            </form>
        @endif

        @forelse ($byDate as $date => $items)
            <x-card :title="\Illuminate\Support\Carbon::parse($date)->translatedFormat('l, j F Y')" :padding="false" wire:key="day-{{ $date }}">
                @foreach ($items as $exam)
                    <div class="flex min-h-14 flex-wrap items-center gap-3 border-t border-border px-4 py-3 first:border-t-0" wire:key="exam-{{ $exam->id }}">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">{{ $exam->assignment->subject->code }} · {{ $exam->assignment->subject->name }}</p>
                            <p class="truncate text-xs text-text-muted">{{ $exam->timeRange() }} · {{ $exam->room?->name ?? 'Ruang belum ditentukan' }} · Kelas {{ $exam->assignment->classLabel() }} · {{ $exam->assignment->teacher->name }}@if ($exam->notes) · {{ $exam->notes }}@endif</p>
                        </div>
                        <x-badge :variant="$exam->type === \App\Enums\ExamType::Midterm ? 'info' : 'warning'">{{ $exam->type->label() }}</x-badge>
                        <div class="flex gap-2">
                            <x-button size="sm" variant="secondary" :href="route('exams.participants', $exam)" wire:navigate>Peserta</x-button>
                            @if ($canManage)
                                <x-button size="sm" variant="secondary" wire:click="edit({{ $exam->id }})">Ubah</x-button>
                                <x-button size="sm" variant="secondary" wire:click="delete({{ $exam->id }})" wire:confirm="Hapus jadwal ujian ini?">Hapus</x-button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </x-card>
        @empty
            <x-card><x-empty-state icon="clipboard-document-check" title="Belum ada jadwal ujian" description="Jadwal UTS dan UAS muncul di sini setelah dibuat." /></x-card>
        @endforelse
    @endif
</div>
