<div x-data="themeSwitcher" class="px-3 py-2" role="group" aria-label="Pilih tema">
    <p class="mb-1.5 text-xs font-medium text-text-muted">Tema</p>
    <div class="grid grid-cols-3 gap-1 rounded-lg bg-surface-muted p-1">
        @foreach (['light' => ['Terang', 'sun'], 'dark' => ['Gelap', 'moon'], 'system' => ['Sistem', 'computer-desktop']] as $value => [$label, $icon])
            <button type="button" @click="set('{{ $value }}')" :aria-pressed="choice === '{{ $value }}'" title="{{ $label }}"
                    class="flex min-h-9 flex-col items-center justify-center gap-0.5 rounded-md px-1 text-[11px] font-medium transition"
                    :class="choice === '{{ $value }}' ? 'bg-surface text-primary shadow-sm' : 'text-text-muted hover:text-text'">
                <x-icon :name="$icon" class="size-4" /> {{ $label }}
            </button>
        @endforeach
    </div>
</div>
