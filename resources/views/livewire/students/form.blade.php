<div class="mx-auto max-w-2xl space-y-6">
    <x-page-header :title="$student ? 'Ubah mahasiswa' : 'Tambah mahasiswa'" />

    <form wire:submit="save" class="space-y-4">
        <x-card title="Identitas">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <x-field label="Nama lengkap" name="name" required>
                        <input id="name" type="text" wire:model="name" class="input" @error('name') aria-invalid="true" @enderror>
                    </x-field>
                </div>
                <x-field label="NIM" name="nim" required>
                    <input id="nim" type="text" wire:model="nim" class="input" inputmode="numeric" @error('nim') aria-invalid="true" @enderror>
                </x-field>
                <x-field label="Program studi" name="program_id">
                    <select id="program_id" wire:model="program_id" class="input">
                        <option value="">Belum ditentukan</option>
                        @foreach ($programs as $program)
                            <option value="{{ $program->id }}">{{ $program->label() }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field label="Angkatan" name="batch_year">
                    <input id="batch_year" type="number" wire:model="batch_year" class="input" inputmode="numeric" min="1990" max="2100">
                </x-field>
                <x-field label="Tanggal lahir" name="birth_date">
                    <input id="birth_date" type="date" wire:model="birth_date" class="input">
                </x-field>
                <x-field label="Jenis kelamin" name="gender">
                    <select id="gender" wire:model="gender" class="input">
                        <option value="">Pilih</option>
                        <option value="L">Laki-laki</option>
                        <option value="P">Perempuan</option>
                    </select>
                </x-field>
            </div>
        </x-card>

        <x-card title="Kontak dan status">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <x-field label="Alamat" name="address">
                        <textarea id="address" wire:model="address" rows="3" class="input"></textarea>
                    </x-field>
                </div>
                <x-field label="Telepon" name="phone">
                    <input id="phone" type="tel" wire:model="phone" class="input" inputmode="tel">
                </x-field>
                <x-field label="Tanggal masuk" name="entry_date">
                    <input id="entry_date" type="date" wire:model="entry_date" class="input">
                </x-field>
                <x-field label="Status" name="status" required>
                    <select id="status" wire:model="status" class="input">
                        @foreach (\App\Enums\StudentStatus::cases() as $option)
                            <option value="{{ $option->value }}">{{ $option->label() }}</option>
                        @endforeach
                    </select>
                </x-field>
            </div>
        </x-card>

        <div class="flex flex-wrap gap-2">
            <x-button type="submit" wire:loading.attr="disabled" wire:target="save">Simpan perubahan</x-button>
            <x-button :href="route('students.index')" wire:navigate variant="secondary">Batal</x-button>
        </div>
    </form>
</div>
