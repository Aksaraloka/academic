<div class="space-y-6">
    <x-page-header :title="$student ? 'Ubah mahasiswa' : 'Tambah mahasiswa'" />

    <form wire:submit="save" class="space-y-4">
        <x-card title="Identitas dan data pribadi">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="flex items-center gap-4 sm:col-span-2">
                    @if ($photo && ! $errors->has('photo'))
                        <img src="{{ $photo->temporaryUrl() }}" alt="Pratinjau pas foto" class="size-20 rounded-full object-cover">
                    @elseif ($student?->photoUrl())
                        <img src="{{ $student->photoUrl() }}" alt="Pas foto {{ $student->name }}" class="size-20 rounded-full object-cover">
                    @else
                        <span class="flex size-20 items-center justify-center rounded-full bg-primary/15 text-text-muted" aria-hidden="true"><x-icon name="user-circle" class="size-10" /></span>
                    @endif
                    <div class="min-w-0 flex-1">
                        <x-field label="Pas foto" name="photo" hint="JPG atau PNG, maksimal 2 MB. Mengganti foto yang ada.">
                            <input id="photo" type="file" wire:model="photo" accept="image/jpeg,image/png" class="input" @error('photo') aria-invalid="true" @enderror>
                        </x-field>
                    </div>
                </div>
                <div class="sm:col-span-2">
                    <x-field label="Nama lengkap" name="name" required>
                        <input id="name" type="text" wire:model="name" class="input" @error('name') aria-invalid="true" @enderror>
                    </x-field>
                </div>
                <x-field label="NIM" name="nim" required>
                    <input id="nim" type="text" wire:model="nim" class="input" inputmode="numeric" @error('nim') aria-invalid="true" @enderror>
                </x-field>
                <x-field label="NIK" name="nik" hint="16 digit sesuai KTP/KK.">
                    <input id="nik" type="text" wire:model="nik" class="input" inputmode="numeric" maxlength="16" @error('nik') aria-invalid="true" @enderror>
                </x-field>
                <x-field label="Tempat lahir" name="birth_place">
                    <input id="birth_place" type="text" wire:model="birth_place" class="input">
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
                <x-field label="Agama" name="religion">
                    <select id="religion" wire:model="religion" class="input">
                        <option value="">Pilih</option>
                        @foreach ($religions as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach
                    </select>
                </x-field>
                <x-field label="Kewarganegaraan" name="nationality">
                    <input id="nationality" type="text" wire:model="nationality" class="input">
                </x-field>
            </div>
        </x-card>

        <x-card title="Alamat sesuai KTP">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <x-field label="Alamat sesuai KTP" name="address">
                        <textarea id="address" wire:model="address" rows="2" class="input"></textarea>
                    </x-field>
                </div>
                <x-field label="Kota/kabupaten" name="city"><input id="city" type="text" wire:model="city" class="input"></x-field>
                <x-field label="Provinsi" name="province"><input id="province" type="text" wire:model="province" class="input"></x-field>
                <x-field label="Kode pos" name="postal_code"><input id="postal_code" type="text" wire:model="postal_code" class="input" inputmode="numeric" maxlength="10"></x-field>
                <x-field label="Telepon" name="phone"><input id="phone" type="tel" wire:model="phone" class="input" inputmode="tel"></x-field>
            </div>
        </x-card>

        <x-card title="Alamat domisili">
            <div class="space-y-4">
                <label class="flex min-h-11 items-start gap-2 text-sm">
                    <input type="checkbox" wire:model.live="is_domicile_same" class="mt-0.5 size-5 shrink-0 accent-primary">
                    <span>Sama dengan alamat KTP <span class="block text-xs text-text-muted">Hilangkan centang bila mahasiswa tinggal di tempat lain, misalnya kos bagi mahasiswa perantau.</span></span>
                </label>

                @unless ($is_domicile_same)
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-field label="Jenis tempat tinggal" name="residence_type">
                            <select id="residence_type" wire:model="residence_type" class="input">
                                <option value="">Pilih</option>
                                @foreach ($residenceTypes as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach
                            </select>
                        </x-field>
                        <div class="hidden sm:block"></div>
                        <div class="sm:col-span-2">
                            <x-field label="Alamat domisili" name="domicile_address" required>
                                <textarea id="domicile_address" wire:model="domicile_address" rows="2" class="input" @error('domicile_address') aria-invalid="true" @enderror></textarea>
                            </x-field>
                        </div>
                        <x-field label="Kota/kabupaten" name="domicile_city" required><input id="domicile_city" type="text" wire:model="domicile_city" class="input" @error('domicile_city') aria-invalid="true" @enderror></x-field>
                        <x-field label="Provinsi" name="domicile_province"><input id="domicile_province" type="text" wire:model="domicile_province" class="input"></x-field>
                        <x-field label="Kode pos" name="domicile_postal_code"><input id="domicile_postal_code" type="text" wire:model="domicile_postal_code" class="input" inputmode="numeric" maxlength="10"></x-field>
                    </div>
                @endunless
            </div>
        </x-card>

        <x-card title="Data akademik">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field label="Program studi" name="program_id">
                    <select id="program_id" wire:model="program_id" class="input">
                        <option value="">Belum ditentukan</option>
                        @foreach ($programs as $program)<option value="{{ $program->id }}">{{ $program->label() }}</option>@endforeach
                    </select>
                </x-field>
                <x-field label="Angkatan" name="batch_year">
                    <input id="batch_year" type="number" wire:model="batch_year" class="input" inputmode="numeric" min="1990" max="2100">
                </x-field>
                <x-field label="Jalur masuk" name="admission_path">
                    <select id="admission_path" wire:model="admission_path" class="input">
                        <option value="">Pilih</option>
                        @foreach ($paths as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach
                    </select>
                </x-field>
                <x-field label="Asal sekolah" name="previous_school">
                    <input id="previous_school" type="text" wire:model="previous_school" class="input" placeholder="SMA Negeri 1 ...">
                </x-field>
                <x-field label="Tanggal masuk" name="entry_date">
                    <input id="entry_date" type="date" wire:model="entry_date" class="input">
                </x-field>
                <x-field label="Golongan UKT" name="ukt_group" hint="Menentukan tarif UKT di Biaya Pendidikan.">
                    <select id="ukt_group" wire:model="ukt_group" class="input">
                        <option value="">Belum ditetapkan</option>
                        @foreach (range(1, 8) as $group)<option value="{{ $group }}">Golongan {{ $group }}</option>@endforeach
                    </select>
                </x-field>
                <x-field label="Status" name="status" required>
                    <select id="status" wire:model="status" class="input">
                        @foreach (\App\Enums\StudentStatus::cases() as $option)<option value="{{ $option->value }}">{{ $option->label() }}</option>@endforeach
                    </select>
                </x-field>
            </div>
        </x-card>

        <x-card title="Nilai awal (riwayat sebelum sistem)">
            <p class="mb-3 text-sm text-text-muted">Untuk mahasiswa pindahan atau yang studinya berjalan sebelum sistem ini dipakai. Nilai ini digabung ke transkrip, IPK, dan syarat wisuda. Kosongkan (0) bila semua nilai sudah diinput lewat dosen.</p>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field label="SKS nilai awal" name="legacy_credits">
                    <input id="legacy_credits" type="number" min="0" max="300" wire:model="legacy_credits" class="input" inputmode="numeric" @error('legacy_credits') aria-invalid="true" @enderror>
                </x-field>
                <x-field label="Total mutu nilai awal" name="legacy_quality_points" hint="Jumlah (SKS × angka mutu) semua mata kuliah awal, mis. 140 SKS × IPK 3,50 = 490.">
                    <input id="legacy_quality_points" type="number" step="0.01" min="0" max="1500" wire:model="legacy_quality_points" class="input" inputmode="decimal" @error('legacy_quality_points') aria-invalid="true" @enderror>
                </x-field>
            </div>
        </x-card>

        <section aria-labelledby="ortu" class="space-y-3">
            <h2 id="ortu" class="text-lg font-semibold">Orang tua / wali</h2>
            <p class="text-sm text-text-muted">Kosongkan nama bila datanya tidak ada. Menghapus nama hanya melepas tautan ke mahasiswa ini.</p>

            @foreach ($parents as $index => $parent)
                <x-card wire:key="parent-{{ $index }}-{{ count($parents) }}">
                    <div class="mb-3 flex items-center justify-between gap-2">
                        <p class="text-sm font-semibold">{{ $parent['relation'] }}</p>
                        @unless (in_array($parent['relation'], ['Ayah', 'Ibu'], true))
                            <x-button size="sm" variant="ghost" wire:click="removeGuardian({{ $index }})"><x-icon name="trash" class="size-4 text-danger" /><span class="sr-only">Hapus wali</span></x-button>
                        @endunless
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <x-field :label="'Nama '.strtolower($parent['relation'])" name="parents.{{ $index }}.name">
                                <input id="parents.{{ $index }}.name" type="text" wire:model="parents.{{ $index }}.name" class="input">
                            </x-field>
                        </div>
                        <x-field label="Pekerjaan" name="parents.{{ $index }}.occupation">
                            <input id="parents.{{ $index }}.occupation" type="text" wire:model="parents.{{ $index }}.occupation" class="input">
                        </x-field>
                        <x-field label="Pendidikan terakhir" name="parents.{{ $index }}.education">
                            <select id="parents.{{ $index }}.education" wire:model="parents.{{ $index }}.education" class="input">
                                <option value="">Pilih</option>
                                @foreach ($educations as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach
                            </select>
                        </x-field>
                        <x-field label="Telepon / WhatsApp" name="parents.{{ $index }}.phone">
                            <input id="parents.{{ $index }}.phone" type="tel" wire:model="parents.{{ $index }}.phone" class="input" inputmode="tel">
                        </x-field>
                        <x-field label="Alamat" name="parents.{{ $index }}.address">
                            <input id="parents.{{ $index }}.address" type="text" wire:model="parents.{{ $index }}.address" class="input">
                        </x-field>
                    </div>
                </x-card>
            @endforeach

            <x-button variant="secondary" icon="plus" wire:click="addGuardian">Tambah wali</x-button>
        </section>

        <div class="flex flex-wrap gap-2">
            <x-button type="submit" wire:loading.attr="disabled" wire:target="save">Simpan perubahan</x-button>
            <x-button :href="route('students.index')" wire:navigate variant="secondary">Batal</x-button>
        </div>
    </form>
</div>
