@php $statusBadge = ['locked' => ['success', 'lock-closed', 'Terkunci'], 'draft' => ['warning', 'pencil-square', 'Draf'], 'empty' => ['neutral', 'clock', 'Belum dimulai']]; @endphp
<div class="space-y-6">
    <x-page-header title="Kelengkapan nilai" subtitle="Pantau kelas yang nilainya sudah dikunci, masih draf, atau terlambat.">
        @if ($isAdmin)
            <x-slot:actions>
                <x-button :href="route('grades.settings')" wire:navigate variant="secondary" icon="cog-6-tooth">Pengaturan nilai</x-button>
            </x-slot:actions>
        @endif
    </x-page-header>

    <div class="max-w-xs">
        <label for="semester" class="mb-1 block text-xs font-medium text-text-muted">Semester</label>
        <select id="semester" wire:model.live="termId" class="input">
            @foreach ($terms as $option)
                <option value="{{ $option->id }}">{{ $option->name }} · {{ $option->semester }}{{ $option->is_active ? ' (aktif)' : '' }}</option>
            @endforeach
        </select>
    </div>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
        <x-stat-tile label="Kelas" :value="$counts['total']" />
        <x-stat-tile label="Terkunci" :value="$counts['locked']" tone="text-success" />
        <x-stat-tile label="Draf" :value="$counts['draft']" :tone="$counts['draft'] > 0 ? 'text-warning' : 'text-text-muted'" />
        <x-stat-tile label="Belum dimulai" :value="$counts['empty']" />
        <x-stat-tile class="col-span-2 sm:col-span-1" label="Terlambat" :value="$counts['overdue']" :tone="$counts['overdue'] > 0 ? 'text-danger' : 'text-success'" :note="$counts['overdue'] > 0 ? 'Lewat batas, belum dikunci' : 'Tidak ada'" />
    </div>

    <x-card :padding="false">
        <table class="table-responsive">
            <thead>
                <tr><th scope="col">Mata kuliah</th><th scope="col">Kelas</th><th scope="col">Dosen</th><th scope="col">Nilai lengkap</th><th scope="col">Batas input</th><th scope="col">Status</th><th scope="col"><span class="sr-only">Aksi</span></th></tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    @php $assignment = $row['assignment']; [$variant, $icon, $label] = $statusBadge[$row['status']]; @endphp
                    <tr wire:key="comp-{{ $assignment->id }}">
                        <td data-label="Mata kuliah" class="font-medium">{{ $assignment->subject->name }}</td>
                        <td data-label="Kelas">{{ $assignment->studyGroup->name }}</td>
                        <td data-label="Dosen" class="text-text-muted">{{ $assignment->teacher->name }}</td>
                        <td data-label="Nilai lengkap" class="tabular-nums">{{ $row['complete'] }}/{{ $row['students'] }}</td>
                        <td data-label="Batas input" class="tabular-nums {{ $row['overdue'] ? 'font-semibold text-danger' : '' }}">
                            {{ $row['deadline']?->translatedFormat('j M Y') ?? '–' }}@if ($row['extended']) <span class="text-xs font-normal text-text-muted">(diperpanjang)</span>@endif
                        </td>
                        <td data-label="Status">
                            <x-badge :variant="$variant" :icon="$icon">{{ $label }}</x-badge>
                            @if ($row['overdue'])<x-badge variant="danger" icon="exclamation-triangle" class="ml-1">Terlambat</x-badge>@endif
                        </td>
                        <td data-label="Aksi" class="sm:text-right">
                            <div class="flex flex-wrap justify-end gap-2">
                                <x-button size="sm" variant="ghost" :href="route('grades.input.sheet', $assignment)" wire:navigate>Lihat</x-button>
                                @if ($isAdmin && ! $row['locked'])
                                    <x-button size="sm" variant="secondary" wire:click="startExtend({{ $assignment->id }})">Perpanjang</x-button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @if ($extendingId === $assignment->id)
                        <tr wire:key="ext-{{ $assignment->id }}" class="bg-surface-muted">
                            <td colspan="7" class="!px-4">
                                <form wire:submit="extend" class="flex flex-wrap items-end gap-3">
                                    <x-field label="Perpanjang batas input sampai" name="extendUntil" required>
                                        <input id="extendUntil" type="date" wire:model="extendUntil" class="input" @error('extendUntil') aria-invalid="true" @enderror>
                                    </x-field>
                                    <x-button type="submit" size="sm">Simpan</x-button>
                                    <x-button size="sm" variant="secondary" wire:click="cancelExtend">Batal</x-button>
                                </form>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="7"><x-empty-state icon="clipboard-document-list" title="Belum ada kelas pada semester ini" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>
</div>
