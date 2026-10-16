<div class="space-y-6">
    <x-page-header title="Fakultas dan prodi" subtitle="Struktur akademik kampus: fakultas, lalu program studi di bawahnya." />

    <div class="grid gap-4 lg:grid-cols-2">
        <form wire:submit="addFaculty">
            <x-card title="Fakultas baru">
                <x-field label="Nama fakultas" name="facultyName" required>
                    <input id="facultyName" type="text" wire:model="facultyName" class="input" placeholder="Fakultas Teknik" @error('facultyName') aria-invalid="true" @enderror>
                </x-field>
                <div class="mt-4"><x-button type="submit" icon="plus">Tambah fakultas</x-button></div>
            </x-card>
        </form>

        <form wire:submit="addProgram">
            <x-card title="Program studi baru">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <x-field label="Fakultas" name="facultyId" required>
                            <select id="facultyId" wire:model="facultyId" class="input">
                                <option value="">Pilih fakultas</option>
                                @foreach ($faculties as $faculty)<option value="{{ $faculty->id }}">{{ $faculty->name }}</option>@endforeach
                            </select>
                        </x-field>
                    </div>
                    <x-field label="Nama prodi" name="programName" required>
                        <input id="programName" type="text" wire:model="programName" class="input" placeholder="Teknik Informatika" @error('programName') aria-invalid="true" @enderror>
                    </x-field>
                    <x-field label="Kode" name="programCode">
                        <input id="programCode" type="text" wire:model="programCode" class="input" placeholder="TI">
                    </x-field>
                    <x-field label="Akreditasi" name="programAccreditation" hint="Mis. Unggul atau B. Tampil di halaman publik Admission.">
                        <input id="programAccreditation" type="text" wire:model="programAccreditation" class="input" maxlength="30" placeholder="Unggul">
                    </x-field>
                    <x-field label="Jenjang" name="degree" required>
                        <select id="degree" wire:model="degree" class="input">
                            @foreach (['D3', 'D4', 'S1', 'S2', 'S3'] as $level)<option value="{{ $level }}">{{ $level }}</option>@endforeach
                        </select>
                    </x-field>
                </div>
                <div class="mt-4"><x-button type="submit" icon="plus">Tambah prodi</x-button></div>
            </x-card>
        </form>
    </div>

    @forelse ($faculties as $faculty)
        <x-card :title="$faculty->name" :padding="false" wire:key="fac-{{ $faculty->id }}">
            @forelse ($faculty->programs as $program)
                <div class="flex min-h-12 items-center gap-3 border-t border-border px-4 py-2 first:border-t-0 hover:bg-surface-muted" wire:key="prog-{{ $program->id }}">
                    <x-icon name="building-library" class="text-primary" />
                    <span class="min-w-0 flex-1 truncate text-sm font-medium">{{ $program->label() }}</span>
                    @if ($program->code)<x-badge>{{ $program->code }}</x-badge>@endif
                    <form wire:submit="saveAccreditation({{ $program->id }})" class="flex items-center gap-2">
                        <label for="accreditation-{{ $program->id }}" class="sr-only">Akreditasi {{ $program->label() }}</label>
                        <input id="accreditation-{{ $program->id }}" type="text" wire:model="accreditations.{{ $program->id }}" class="input !w-32" maxlength="30" placeholder="Akreditasi"
                               @error('accreditations.'.$program->id) aria-invalid="true" @enderror>
                        <x-button type="submit" variant="secondary" wire:loading.attr="disabled" wire:target="saveAccreditation({{ $program->id }})">Simpan</x-button>
                    </form>
                </div>
            @empty
                <p class="px-4 py-4 text-sm text-text-muted">Belum ada program studi di fakultas ini.</p>
            @endforelse
        </x-card>
    @empty
        <x-card><x-empty-state icon="building-library" title="Belum ada fakultas" description="Mulai dengan menambahkan fakultas, lalu program studi di bawahnya." /></x-card>
    @endforelse
</div>
