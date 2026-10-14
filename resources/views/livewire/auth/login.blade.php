<div>
    <h1 class="text-2xl font-bold sm:text-3xl">Masuk</h1>
    <p class="mt-1 text-sm text-text-muted">Gunakan akun Aksaraloka ID kamu.</p>

    @if (session('status'))
        <div class="mt-4 rounded-lg border border-border bg-surface-muted p-3 text-sm" role="status">{{ session('status') }}</div>
    @endif

    <form wire:submit="login" class="mt-6 space-y-4">
        <x-field label="Email" name="email" required>
            <input id="email" type="email" wire:model="email" class="input" autocomplete="username" inputmode="email" autofocus @error('email') aria-invalid="true" @enderror>
        </x-field>

        <x-field label="Kata sandi" name="password" required>
            <input id="password" type="password" wire:model="password" class="input" autocomplete="current-password" @error('password') aria-invalid="true" @enderror>
        </x-field>

        <div class="flex flex-wrap items-center justify-between gap-x-4">
            <label class="flex min-h-11 items-center gap-2 text-sm">
                <input type="checkbox" wire:model="remember" class="size-5 accent-primary">
                Ingat saya di perangkat ini
            </label>
            <a href="{{ route('password.request') }}" wire:navigate class="flex min-h-11 items-center text-sm font-medium text-primary hover:underline">Lupa kata sandi?</a>
        </div>

        <x-button type="submit" class="w-full" wire:loading.attr="disabled" wire:target="login">
            <span wire:loading.remove wire:target="login">Masuk</span>
            <span wire:loading wire:target="login">Memproses…</span>
        </x-button>
    </form>
</div>
