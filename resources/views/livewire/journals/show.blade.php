<div class="space-y-6">
    <x-page-header :title="$assignment->subject->name" :subtitle="$assignment->subject->code.' · Kelas '.$assignment->classLabel().' · '.$assignment->teacher->name.' · '.$assignment->academicTerm->name.' '.$assignment->academicTerm->semester">
        <x-slot:actions>
            <x-button :href="route('journals.index', ['term' => $assignment->academic_term_id])" wire:navigate variant="secondary" icon="arrow-left">Jurnal kuliah</x-button>
            @if ($canPrint)<x-button :href="route('journals.pdf', $assignment)" icon="arrow-down-tray" download>BAP PDF</x-button>@endif
        </x-slot:actions>
    </x-page-header>

    <p class="text-sm text-text-muted">{{ $filled }} dari {{ $meetings->count() }} pertemuan sudah punya jurnal.</p>

    @if ($canEdit && $editingSessionId)
        <form wire:submit="save">
            <x-card title="Isi atau ubah jurnal">
                <div class="grid gap-4">
                    <x-field label="Pokok bahasan" name="topic" required><input id="topic" type="text" wire:model="topic" class="input" @error('topic') aria-invalid="true" @enderror></x-field>
                    <x-field label="Materi yang diajarkan" name="material"><textarea id="material" wire:model="material" rows="3" class="input"></textarea></x-field>
                    <x-field label="Catatan" name="notes" hint="Tugas, kendala, atau tindak lanjut."><input id="notes" type="text" wire:model="notes" class="input"></x-field>
                </div>
                <div class="mt-4 flex gap-2"><x-button type="submit" wire:loading.attr="disabled" wire:target="save">Simpan jurnal</x-button><x-button type="button" variant="secondary" wire:click="cancel">Batal</x-button></div>
            </x-card>
        </form>
    @endif

    <x-card :padding="false">
        @forelse ($meetings->reverse() as $meeting)
            @php $session = $meeting['session']; $journal = $meeting['journal']; @endphp
            <div class="border-t border-border px-4 py-3 first:border-t-0" wire:key="m-{{ $session->id }}">
                <div class="flex flex-wrap items-center gap-3">
                    <x-badge>Pertemuan {{ $meeting['number'] }}</x-badge>
                    <span class="text-sm text-text-muted">{{ $session->date->translatedFormat('l, j F Y') }}</span>
                    <span class="text-xs text-text-muted tabular-nums">hadir {{ $meeting['present'] }}/{{ $meeting['total'] }}</span>
                    @if ($canEdit)<x-button size="sm" variant="secondary" class="ml-auto" wire:click="edit({{ $session->id }})">{{ $journal ? 'Ubah' : 'Isi jurnal' }}</x-button>@endif
                </div>
                @if ($journal)
                    <p class="mt-2 text-sm font-medium">{{ $journal->topic }}</p>
                    @if ($journal->material)<p class="mt-1 whitespace-pre-line text-sm">{{ $journal->material }}</p>@endif
                    @if ($journal->notes)<p class="mt-1 text-xs text-text-muted">Catatan: {{ $journal->notes }}</p>@endif
                @else
                    <p class="mt-2 text-sm text-warning">Jurnal belum diisi.</p>
                @endif
            </div>
        @empty
            <x-empty-state icon="clipboard-document-list" title="Belum ada pertemuan" description="Pertemuan muncul setelah dosen membuka presensi." />
        @endforelse
    </x-card>
</div>
