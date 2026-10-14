<div class="space-y-6">
    <x-page-header title="Perwalian" subtitle="Catatan bimbingan akademik mahasiswa perwalian.">
        <x-slot:actions>
            <x-button :href="route('enrollment.approvals')" wire:navigate variant="secondary" icon="clipboard-document-list">Persetujuan KRS</x-button>
        </x-slot:actions>
    </x-page-header>

    @if (! $term)
        <x-card><x-empty-state icon="calendar-days" title="Belum ada semester aktif" description="Perwalian mengikuti semester aktif." /></x-card>
    @else
        <div class="grid gap-4 lg:grid-cols-3">
            <x-card title="Mahasiswa" :padding="false" class="lg:col-span-1">
                <div class="border-b border-border p-3">
                    <input type="search" wire:model.live.debounce.300ms="search" class="input" placeholder="Cari nama atau NIM" aria-label="Cari mahasiswa">
                </div>
                @forelse ($students as $row)
                    <button type="button" wire:click="select({{ $row->id }})" wire:key="stu-{{ $row->id }}"
                            class="flex min-h-12 w-full items-center gap-3 border-t border-border px-4 py-2 text-left first:border-t-0 hover:bg-surface-muted {{ $student?->id === $row->id ? 'bg-primary/10' : '' }}">
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium">{{ $row->name }}</span>
                            <span class="block truncate text-xs text-text-muted">{{ $row->nim }}</span>
                        </span>
                        <x-badge>{{ $row->advising_notes_count }} catatan</x-badge>
                    </button>
                @empty
                    <p class="px-4 py-4 text-sm text-text-muted">Tidak ada mahasiswa perwalian yang cocok.</p>
                @endforelse
            </x-card>

            <div class="space-y-4 lg:col-span-2">
                @if (! $student)
                    <x-card><x-empty-state icon="user-group" title="Pilih mahasiswa" description="Pilih mahasiswa di daftar untuk melihat dan mencatat bimbingan." /></x-card>
                @else
                    <x-card>
                        <div class="flex flex-wrap items-center gap-3">
                            <div class="min-w-0 flex-1">
                                <p class="text-base font-semibold">{{ $student->name }}</p>
                                <p class="text-xs text-text-muted">{{ $student->nim }}</p>
                            </div>
                            <x-badge :variant="$student->status->variant()">{{ $student->status->label() }}</x-badge>
                            @if ($enrollment)<x-badge :variant="$enrollment->status->variant()">KRS: {{ $enrollment->status->label() }}</x-badge>@endif
                            <x-button size="sm" variant="secondary" :href="route('grades.transcript', ['student' => $student->id])" wire:navigate>Transkrip</x-button>
                        </div>
                    </x-card>

                    @if ($canWrite)
                        <form wire:submit="saveNote">
                            <x-card :title="$editingId ? 'Ubah catatan' : 'Catatan bimbingan baru'">
                                <div class="grid gap-4 sm:grid-cols-3">
                                    <x-field label="Tanggal" name="held_on" required><input id="held_on" type="date" wire:model="held_on" class="input" max="{{ today()->toDateString() }}"></x-field>
                                    <div class="sm:col-span-2">
                                        <x-field label="Topik" name="topic" required><input id="topic" type="text" wire:model="topic" class="input" placeholder="Rencana studi, kendala nilai, dll." @error('topic') aria-invalid="true" @enderror></x-field>
                                    </div>
                                    <div class="sm:col-span-3">
                                        <x-field label="Catatan" name="notes" required><textarea id="notes" wire:model="notes" rows="3" class="input" @error('notes') aria-invalid="true" @enderror></textarea></x-field>
                                    </div>
                                    <div class="sm:col-span-3">
                                        <x-field label="Tindak lanjut" name="follow_up" hint="Opsional. Terlihat oleh mahasiswa."><input id="follow_up" type="text" wire:model="follow_up" class="input"></x-field>
                                    </div>
                                </div>
                                <div class="mt-4 flex gap-2">
                                    <x-button type="submit" wire:loading.attr="disabled" wire:target="saveNote">{{ $editingId ? 'Simpan perubahan' : 'Simpan catatan' }}</x-button>
                                    @if ($editingId)<x-button type="button" variant="secondary" wire:click="cancelEdit">Batal</x-button>@endif
                                </div>
                            </x-card>
                        </form>
                    @else
                        <p class="text-sm text-text-muted">Hanya dosen wali mahasiswa ini yang bisa menambah catatan.</p>
                    @endif

                    <x-card title="Riwayat bimbingan" :padding="false">
                        @forelse ($history as $note)
                            <div class="border-t border-border px-4 py-3 first:border-t-0" wire:key="note-{{ $note->id }}">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="flex-1 text-sm font-medium">{{ $note->topic }}</p>
                                    <span class="text-xs text-text-muted">{{ $note->held_on->translatedFormat('j F Y') }} · {{ $note->teacher->name }}</span>
                                    @if ($canWrite && $note->teacher_id === $myTeacherId)<x-button size="sm" variant="secondary" wire:click="edit({{ $note->id }})">Ubah</x-button>@endif
                                </div>
                                <p class="mt-1 whitespace-pre-line text-sm">{{ $note->notes }}</p>
                                @if ($note->follow_up)<p class="mt-1 text-xs text-text-muted">Tindak lanjut: {{ $note->follow_up }}</p>@endif
                            </div>
                        @empty
                            <p class="px-4 py-4 text-sm text-text-muted">Belum ada catatan bimbingan.</p>
                        @endforelse
                    </x-card>
                @endif
            </div>
        </div>
    @endif
</div>
