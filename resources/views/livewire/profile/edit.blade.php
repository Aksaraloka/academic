<div class="space-y-6">
    <x-page-header title="Ubah profil" subtitle="Perbarui kontak, alamat domisili, dan kata sandimu.">
        <x-slot:actions>
            <x-button :href="route('profile.show')" wire:navigate variant="secondary" icon="arrow-left">Profil</x-button>
        </x-slot:actions>
    </x-page-header>

    <form wire:submit="saveContact" class="space-y-4">
        <div class="grid gap-4 lg:grid-cols-2">
        <x-card title="Kontak">
            <x-field label="Telepon / WhatsApp" name="phone">
                <input id="phone" type="tel" wire:model="phone" class="input sm:max-w-sm" inputmode="tel" autocomplete="tel" @error('phone') aria-invalid="true" @enderror>
            </x-field>
        </x-card>

            @if ($student)
            <x-card title="Alamat sesuai KTP">
                <p class="text-sm font-medium">{{ $student->address ?: '–' }}</p>
                <p class="mt-1 text-sm text-text-muted">{{ collect([$student->city, $student->province, $student->postal_code])->filter()->implode(', ') ?: '–' }}</p>
                <p class="mt-3 text-xs text-text-muted">Alamat KTP hanya bisa diubah BAAK. Ajukan lewat menu Masukan bila ada yang keliru.</p>
            </x-card>
            @endif
        </div>

        @if ($student)

            <x-card title="Alamat domisili">
                <div class="space-y-4">
                    <label class="flex min-h-11 items-start gap-2 text-sm">
                        <input type="checkbox" wire:model.live="is_domicile_same" class="mt-0.5 size-5 shrink-0 accent-primary">
                        <span>Sama dengan alamat KTP <span class="block text-xs text-text-muted">Hilangkan centang bila kamu tinggal di tempat lain, misalnya kos atau asrama.</span></span>
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
                                <x-field label="Alamat domisili" name="domicile_address" required hint="Alamat lengkap tempat kamu tinggal sekarang.">
                                    <textarea id="domicile_address" wire:model="domicile_address" rows="2" class="input" autocomplete="street-address" @error('domicile_address') aria-invalid="true" @enderror></textarea>
                                </x-field>
                            </div>
                            <x-field label="Kota/kabupaten" name="domicile_city" required><input id="domicile_city" type="text" wire:model="domicile_city" class="input" @error('domicile_city') aria-invalid="true" @enderror></x-field>
                            <x-field label="Provinsi" name="domicile_province"><input id="domicile_province" type="text" wire:model="domicile_province" class="input"></x-field>
                            <x-field label="Kode pos" name="domicile_postal_code"><input id="domicile_postal_code" type="text" wire:model="domicile_postal_code" class="input" inputmode="numeric" maxlength="10"></x-field>
                        </div>
                    @endunless
                </div>
            </x-card>
        @endif

        <p class="text-xs text-text-muted">Nama, NIM, NIK, tanggal lahir, alamat KTP, dan data akademik hanya bisa diubah BAAK.</p>
        <x-button type="submit" wire:loading.attr="disabled" wire:target="saveContact">Simpan perubahan</x-button>
    </form>

    <form wire:submit="savePassword">
        <x-card title="Kata sandi">
            <div class="space-y-4">
                <x-field label="Kata sandi saat ini" name="current_password" required>
                    <input id="current_password" type="password" wire:model="current_password" class="input" autocomplete="current-password" @error('current_password') aria-invalid="true" @enderror>
                </x-field>
                <x-field label="Kata sandi baru" name="password" required hint="Minimal 8 karakter.">
                    <input id="password" type="password" wire:model="password" class="input" autocomplete="new-password" @error('password') aria-invalid="true" @enderror>
                </x-field>
                <x-field label="Ulangi kata sandi baru" name="password_confirmation" required>
                    <input id="password_confirmation" type="password" wire:model="password_confirmation" class="input" autocomplete="new-password">
                </x-field>
            </div>
            <div class="mt-4"><x-button type="submit" wire:loading.attr="disabled" wire:target="savePassword">Ganti kata sandi</x-button></div>
        </x-card>
    </form>
</div>
