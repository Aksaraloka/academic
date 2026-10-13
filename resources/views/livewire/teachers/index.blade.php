<div class="space-y-6">
    <x-page-header title="Dosen" subtitle="Data dosen dan dosen.">
        <x-slot:actions>
            <x-button wire:click="$toggle('showForm')" icon="plus">Tambah dosen</x-button>
        </x-slot:actions>
    </x-page-header>

    @if ($showForm)
        <form wire:submit="save">
            <x-card title="Dosen baru">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field label="Nama lengkap" name="name" required>
                        <input id="name" type="text" wire:model="name" class="input" @error('name') aria-invalid="true" @enderror>
                    </x-field>
                    <x-field label="Email" name="email" required>
                        <input id="email" type="email" wire:model="email" class="input" @error('email') aria-invalid="true" @enderror>
                    </x-field>
                    <x-field label="NIDN / NIP" name="nip">
                        <input id="nip" type="text" wire:model="nip" class="input">
                    </x-field>
                    <x-field label="Status kepegawaian" name="employment_status">
                        <input id="employment_status" type="text" wire:model="employment_status" class="input" placeholder="PNS, Honorer, ...">
                    </x-field>
                </div>
                <div class="mt-4 flex flex-wrap gap-2">
                    <x-button type="submit">Simpan perubahan</x-button>
                    <x-button variant="secondary" wire:click="$set('showForm', false)">Batal</x-button>
                </div>
            </x-card>
        </form>
    @endif

    <x-card :padding="false">
        @if ($teachers->isEmpty())
            <x-empty-state icon="identification" title="Belum ada dosen" description="Tambahkan dosen agar bisa ditugaskan ke kelas dan jadwal." />
        @else
            <ul class="divide-y divide-border">
                @foreach ($teachers as $teacher)
                    <li class="flex min-h-14 items-center gap-3 px-4 py-2.5 hover:bg-surface-muted" wire:key="teacher-{{ $teacher->id }}">
                        <x-icon name="identification" class="text-primary" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">{{ $teacher->name }}</p>
                            <p class="truncate text-xs text-text-muted">{{ $teacher->user?->email }}@if ($teacher->nip) · {{ $teacher->nip }}@endif</p>
                        </div>
                        @if ($teacher->employment_status)<x-badge>{{ $teacher->employment_status }}</x-badge>@endif
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>
</div>
