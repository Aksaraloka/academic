@props(['variant' => 'dropdown'])
@php
    $icons = ['academic' => 'academic-cap', 'lms' => 'book-open', 'admission' => 'user-plus'];
    $apps = auth()->check() ? app(\App\Services\IntegrationApps::class)->linksFor(auth()->user()) : [];
@endphp
{{-- Tanpa aplikasi lain yang terhubung dan boleh diakses, tidak ada yang perlu dialihkan. --}}
@if (count($apps) > 1)
    @if ($variant === 'list')
        {{-- Di HP: bagian dari menu akun, bukan bar bawah. --}}
        <div class="lg:hidden" role="group" aria-labelledby="app-switcher-title">
            <p id="app-switcher-title" class="px-3 pb-1 pt-2 text-xs font-semibold uppercase tracking-wide text-text-muted">Aplikasi Aksaraloka</p>
            @foreach ($apps as $app)
                @if ($app['key'] === 'academic')
                    <p class="flex min-h-11 items-center gap-2 rounded-md px-3 text-sm font-semibold text-primary" aria-current="true">
                        <x-icon :name="$icons[$app['key']]" class="size-4" /> {{ $app['label'] }} <span class="sr-only">(sedang dibuka)</span>
                    </p>
                @else
                    <a href="{{ $app['url'] }}/auth/academic" role="menuitem" class="flex min-h-11 w-full items-center gap-2 rounded-md px-3 text-sm hover:bg-surface-muted">
                        <x-icon :name="$icons[$app['key']] ?? 'squares-2x2'" class="size-4" /> {{ $app['label'] }}
                    </a>
                @endif
            @endforeach
            <div class="my-1 border-t border-border" role="separator"></div>
        </div>
    @else
        <div x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false" class="relative hidden lg:block">
            <button type="button" @click="open = !open" aria-haspopup="menu" :aria-expanded="open"
                class="flex size-11 items-center justify-center rounded-lg text-text-muted hover:bg-surface-muted hover:text-text">
                <x-icon name="squares-2x2" /><span class="sr-only">Aplikasi Aksaraloka</span>
            </button>
            <div x-show="open" x-cloak x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                role="menu" aria-label="Aplikasi Aksaraloka"
                class="absolute right-0 z-30 mt-1 min-w-56 origin-top-right rounded-lg border border-border bg-surface p-1 shadow-sm">
                <p class="px-3 pb-1 pt-2 text-xs font-semibold uppercase tracking-wide text-text-muted">Aplikasi Aksaraloka</p>
                @foreach ($apps as $app)
                    @if ($app['key'] === 'academic')
                        <p class="flex min-h-11 items-center gap-2 rounded-md px-3 text-sm font-semibold text-primary" aria-current="true">
                            <x-icon :name="$icons[$app['key']]" class="size-4" /> {{ $app['label'] }}
                            <x-badge variant="primary" class="ml-auto">Dibuka</x-badge>
                        </p>
                    @else
                        <a href="{{ $app['url'] }}/auth/academic" role="menuitem" class="flex min-h-11 w-full items-center gap-2 rounded-md px-3 text-sm hover:bg-surface-muted">
                            <x-icon :name="$icons[$app['key']] ?? 'squares-2x2'" class="size-4" /> {{ $app['label'] }}
                        </a>
                    @endif
                @endforeach
            </div>
        </div>
    @endif
@endif
