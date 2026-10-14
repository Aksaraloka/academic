<div>
    <h1 class="text-2xl font-bold sm:text-3xl">Lupa kata sandi</h1>
    <p class="mt-1 text-sm text-text-muted">Masukkan email akunmu. Kami kirim tautan untuk membuat kata sandi baru.</p>

    @if ($sent)
        <div class="mt-6 rounded-lg border border-border bg-surface-muted p-4 text-sm" role="status">
            Jika email terdaftar, tautan pengaturan ulang kata sandi sudah dikirim. Tautan berlaku 60 menit.
        </div>
    @else
        <form wire:submit="sendLink" class="mt-6 space-y-4">
            <x-field label="Email" name="email" required>
                <input id="email" type="email" wire:model="email" class="input" autocomplete="username" inputmode="email" autofocus @error('email') aria-invalid="true" @enderror>
            </x-field>

            <x-button type="submit" class="w-full" wire:loading.attr="disabled" wire:target="sendLink">
                <span wire:loading.remove wire:target="sendLink">Kirim tautan</span>
                <span wire:loading wire:target="sendLink">Memproses…</span>
            </x-button>
        </form>
    @endif

    <p class="mt-6 text-sm">
        <a href="{{ route('login') }}" wire:navigate class="font-medium text-primary hover:underline">Kembali ke halaman masuk</a>
    </p>
</div>
