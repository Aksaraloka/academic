<div>
    <h1 class="text-2xl font-bold sm:text-3xl">Atur ulang kata sandi</h1>
    <p class="mt-1 text-sm text-text-muted">Buat kata sandi baru minimal 8 karakter.</p>

    <form wire:submit="resetPassword" class="mt-6 space-y-4">
        <x-field label="Email" name="email" required>
            <input id="email" type="email" wire:model="email" class="input" autocomplete="username" inputmode="email" @error('email') aria-invalid="true" @enderror>
        </x-field>

        <x-field label="Kata sandi baru" name="password" required>
            <input id="password" type="password" wire:model="password" class="input" autocomplete="new-password" autofocus @error('password') aria-invalid="true" @enderror>
        </x-field>

        <x-field label="Ulangi kata sandi baru" name="password_confirmation" required>
            <input id="password_confirmation" type="password" wire:model="password_confirmation" class="input" autocomplete="new-password">
        </x-field>

        <x-button type="submit" class="w-full" wire:loading.attr="disabled" wire:target="resetPassword">
            <span wire:loading.remove wire:target="resetPassword">Simpan kata sandi</span>
            <span wire:loading wire:target="resetPassword">Memproses…</span>
        </x-button>
    </form>
</div>
