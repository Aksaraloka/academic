<div class="space-y-6">
    <x-page-header title="Kelas" :subtitle="$term ? $term->name.' · semester '.$term->semester : 'Belum ada tahun ajaran aktif'">
        @if (auth()->user()->isAdmin() && $term)
            <x-slot:actions>
                <x-button wire:click="$toggle('showForm')" icon="plus">Tambah kelas</x-button>
            </x-slot:actions>
        @endif
    </x-page-header>

    @if ($showForm)
        <form wire:submit="save">
            <x-card title="Kelas baru">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field label="Nama kelas" name="name" required>
                        <input id="name" type="text" wire:model="name" class="input" placeholder="TI-2024-A" @error('name') aria-invalid="true" @enderror>
                    </x-field>
                    <x-field label="Program studi" name="program_id">
                        <select id="program_id" wire:model="program_id" class="input">
                            <option value="">Belum ditentukan</option>
                            @foreach ($programs as $program)<option value="{{ $program->id }}">{{ $program->label() }}</option>@endforeach
                        </select>
                    </x-field>
                    <x-field label="Dosen wali" name="homeroom_teacher_id">
                        <select id="homeroom_teacher_id" wire:model="homeroom_teacher_id" class="input">
                            <option value="">Belum ditentukan</option>
                            @foreach ($teachers as $teacher)
                                <option value="{{ $teacher->id }}">{{ $teacher->name }}</option>
                            @endforeach
                        </select>
                    </x-field>
                </div>
                <div class="mt-4 flex flex-wrap gap-2">
                    <x-button type="submit">Simpan perubahan</x-button>
                    <x-button variant="secondary" wire:click="$set('showForm', false)">Batal</x-button>
                </div>
            </x-card>
        </form>
    @endif

    @if ($groups->isEmpty())
        <x-card><x-empty-state icon="user-group" title="Belum ada kelas" description="Buat kelas pertama, lalu tempatkan mahasiswa ke dalamnya." /></x-card>
    @else
        <div class="grid grid-cols-[repeat(auto-fill,minmax(14rem,1fr))] gap-4">
            @foreach ($groups as $group)
                <a href="{{ route('groups.show', $group) }}" wire:navigate wire:key="group-{{ $group->id }}"
                   class="block overflow-hidden rounded-card border border-border bg-surface transition hover:border-primary">
                    <div class="flex h-16 items-center bg-brand px-4 text-lg font-bold text-brand-fg">{{ $group->name }}</div>
                    <div class="p-4">
                        <p class="truncate text-xs text-text-muted">{{ $group->program?->label() ?? 'Program studi belum ditentukan' }}</p>
                        <p class="mt-3 truncate text-xs text-text-muted">Dosen wali</p>
                        <p class="truncate text-sm font-semibold">{{ $group->homeroomTeacher?->name ?? 'Belum ditentukan' }}</p>
                        <p class="mt-3 text-xs text-text-muted">Mahasiswa</p>
                        <p class="text-base font-bold tabular-nums">{{ $group->students_count }}</p>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
