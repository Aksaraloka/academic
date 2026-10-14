<div class="space-y-6">
    <x-page-header title="Mahasiswa" subtitle="Data induk mahasiswa yang dipakai semua aplikasi Aksaraloka.">
        @if (auth()->user()->isAdmin())
            <x-slot:actions>
                <x-button :href="route('students.import')" wire:navigate variant="secondary" icon="arrow-up-tray">Impor</x-button>
                <x-button :href="route('students.create')" wire:navigate icon="plus">Tambah mahasiswa</x-button>
            </x-slot:actions>
        @endif
    </x-page-header>

    <div class="flex flex-col gap-3 sm:flex-row">
        <div class="relative flex-1">
            <label for="cari" class="sr-only">Cari mahasiswa</label>
            <x-icon name="magnifying-glass" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-text-muted" />
            <input id="cari" type="search" wire:model.live.debounce.300ms="search" class="input pl-10" placeholder="Cari nama, NIM, atau program studi">
        </div>
        <div>
            <label for="status" class="sr-only">Status</label>
            <select id="status" wire:model.live="status" class="input sm:w-44">
                <option value="">Semua status</option>
                @foreach (\App\Enums\StudentStatus::cases() as $status)
                    <option value="{{ $status->value }}">{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <x-card :padding="false">
        @if ($students->isEmpty())
            <x-empty-state icon="users" title="Belum ada mahasiswa"
                :description="$search !== '' ? 'Tidak ada hasil untuk pencarianmu. Coba kata kunci lain.' : 'Tambahkan mahasiswa satu per satu atau impor dari berkas CSV.'">
                @if (auth()->user()->isAdmin() && $search === '')
                    <x-button :href="route('students.import')" wire:navigate variant="secondary" icon="arrow-up-tray">Impor CSV</x-button>
                    <x-button :href="route('students.create')" wire:navigate icon="plus">Tambah mahasiswa</x-button>
                @endif
            </x-empty-state>
        @else
            <table class="table-responsive">
                <thead>
                    <tr>
                        <th scope="col">Nama</th>
                        <th scope="col">NIM</th>
                        <th scope="col">Program studi</th>
                        <th scope="col">Angkatan</th>
                        <th scope="col">Status</th>
                        <th scope="col"><span class="sr-only">Aksi</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($students as $student)
                        <tr wire:key="student-{{ $student->id }}" class="hover:bg-surface-muted">
                            <td data-label="Nama" class="font-medium">{{ $student->name }} @if ($student->isMigrant())<x-badge variant="info" class="ml-1">Perantau</x-badge>@endif</td>
                            <td data-label="NIM" class="tabular-nums text-text-muted">{{ $student->nim ?? '–' }}</td>
                            <td data-label="Program studi" class="text-text-muted">{{ $student->program?->label() ?? '–' }}</td>
                            <td data-label="Angkatan" class="tabular-nums text-text-muted">{{ $student->batch_year ?? '–' }}</td>
                            <td data-label="Status"><x-badge :variant="$student->status->variant()">{{ $student->status->label() }}</x-badge></td>
                            <td data-label="Aksi" class="sm:text-right">
                                <div class="flex flex-wrap justify-end gap-1">
                                    @if (auth()->user()->hasRole(\App\Enums\Role::Admin, \App\Enums\Role::Principal, \App\Enums\Role::Curriculum))
                                        <x-button :href="route('grades.transcript', ['student' => $student->id])" wire:navigate variant="ghost" size="sm" icon="document-text">Transkrip</x-button>
                                    @endif
                                    @if (auth()->user()->isAdmin())
                                        <x-button :href="route('students.edit', $student)" wire:navigate variant="ghost" size="sm" icon="pencil-square">Ubah</x-button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-card>

    {{ $students->links() }}
</div>
