<div class="mx-auto max-w-2xl space-y-6">
    <x-page-header title="Ubah profil" subtitle="Perbarui kontak dan kata sandimu.">
        <x-slot:actions>
            <x-button :href="route('profile.show')" wire:navigate variant="secondary" icon="arrow-left">Profil</x-button>
        </x-slot:actions>
    </x-page-header>

    <form wire:submit="saveContact">
        <x-card title="Kontak">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field label="Telepon / WhatsApp" name="phone">
                    <input id="phone" type="tel" wire:model="phone" class="input" inputmode="tel" autocomplete="tel" @error('phone') aria-invalid="true" @enderror>
                </x-field>
                <div class="hidden sm:block"></div>
                @if ($isStudent)
                    <div class="sm:col-span-2">
                        <x-field label="Alamat" name="address">
                            <textarea id="address" wire:model="address" rows="2" class="input" autocomplete="street-address"></textarea>
                        </x-field>
                    </div>
                    <x-field label="Kota/kabupaten" name="city"><input id="city" type="text" wire:model="city" class="input"></x-field>
                    <x-field label="Provinsi" name="province"><input id="province" type="text" wire:model="province" class="input"></x-field>
                    <x-field label="Kode pos" name="postal_code"><input id="postal_code" type="text" wire:model="postal_code" class="input" inputmode="numeric" maxlength="10"></x-field>
                @endif
            </div>
            @if ($isStudent)
                <p class="mt-4 text-xs text-text-muted">Nama, NIM, NIK, tanggal lahir, dan data akademik hanya bisa diubah BAAK.</p>
            @endif
            <div class="mt-4"><x-button type="submit" wire:loading.attr="disabled" wire:target="saveContact">Simpan perubahan</x-button></div>
        </x-card>
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
