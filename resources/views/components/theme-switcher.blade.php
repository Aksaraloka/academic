<div x-data="themeSwitcher">
    <div x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false" class="relative">
        <button type="button" @click="open = !open" class="inline-flex size-11 items-center justify-center rounded-lg text-text-muted hover:bg-surface-muted" aria-haspopup="menu" :aria-expanded="open">
            <span class="sr-only">Pilih tema</span>
            <x-icon name="sun" class="size-5 dark:hidden" />
            <x-icon name="moon" class="hidden size-5 dark:block" />
        </button>
        <div x-show="open" x-cloak x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" role="menu" class="absolute right-0 z-30 mt-1 min-w-48 origin-top-right rounded-lg border border-border bg-surface p-1 shadow-sm">
            @foreach (['light' => ['Terang', 'sun'], 'dark' => ['Gelap', 'moon'], 'system' => ['Ikuti sistem', 'computer-desktop']] as $value => [$label, $icon])
                <button type="button" role="menuitem" @click="set('{{ $value }}'); open = false" class="flex min-h-10 w-full items-center gap-2 rounded-md px-3 text-sm hover:bg-surface-muted" :class="choice === '{{ $value }}' && 'font-semibold text-primary'">
                    <x-icon :name="$icon" class="size-4" /> {{ $label }}
                </button>
            @endforeach
        </div>
    </div>
</div>
