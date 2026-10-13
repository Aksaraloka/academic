<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#c62828">
    <title>{{ $title ?? 'Aksaraloka Academic' }} · {{ auth()->user()?->tenant?->name ?? 'Aksaraloka' }}</title>
    @include('partials.theme-script')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full">
@php
    $user = auth()->user();
    $items = \App\Support\Navigation::for($user);
    $term = \App\Models\AcademicTerm::current();
    $badges = [
        'feedback.index' => \App\Models\FeedbackTicket::isHandler($user)
            ? \App\Models\FeedbackTicket::query()->where('status', 'baru')->count()
            : 0,
    ];
    $flat = \App\Support\Navigation::flatten($items);
    $primaryItems = array_slice($flat, 0, count($flat) > 5 ? 4 : 5);
    $moreItems = count($flat) > 5 ? array_slice($flat, 4) : [];
@endphp
<a href="#konten" class="sr-only focus:not-sr-only focus:fixed focus:left-2 focus:top-2 focus:z-50 focus:rounded-lg focus:bg-surface focus:px-3 focus:py-2">Lewati ke konten</a>

<div x-data="{ sheet: false, collapsed: false }" @keydown.escape.window="sheet = false">
    {{-- Sidebar desktop --}}
    <aside class="fixed inset-y-0 left-0 z-20 hidden flex-col border-r border-border bg-surface transition-[width] lg:flex" :class="collapsed ? 'w-[4.5rem]' : 'w-64'" aria-label="Navigasi utama">
        <div class="flex h-16 shrink-0 items-center gap-3 border-b border-border px-4">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary text-primary-fg"><x-icon name="academic-cap" class="size-6" /></span>
            <span x-show="!collapsed" class="min-w-0 truncate font-bold">Aksaraloka <span class="font-medium text-text-muted">Academic</span></span>
        </div>
        <nav class="flex-1 space-y-1 overflow-y-auto p-3">
            @foreach ($items as $item)
                @if (isset($item['children']))
                    @php $groupActive = request()->routeIs($item['match']); @endphp
                    <div x-data="{ open: {{ $groupActive ? 'true' : 'false' }} }">
                        <button type="button" @click="collapsed ? (collapsed = false, open = true) : open = !open" :aria-expanded="open"
                                title="{{ $item['label'] }}"
                                class="flex min-h-11 w-full items-center gap-3 rounded-lg px-3 text-sm font-medium transition {{ $groupActive ? 'bg-primary/15 text-primary' : 'text-text-muted hover:bg-surface-muted' }}">
                            <x-icon :name="$item['icon']" />
                            <span x-show="!collapsed" class="truncate">{{ $item['label'] }}</span>
                            <x-icon name="chevron-down" class="ml-auto size-4 transition" x-show="!collapsed" ::class="open && 'rotate-180'" />
                        </button>
                        <div x-show="open && !collapsed" x-cloak class="mt-1 space-y-1 border-l border-border pl-3 ml-5">
                            @foreach ($item['children'] as $child)
                                @php $childActive = request()->routeIs($child['match']); @endphp
                                <a href="{{ route($child['route']) }}" wire:navigate @if ($childActive) aria-current="page" @endif
                                   class="flex min-h-10 items-center rounded-lg px-3 text-sm transition {{ $childActive ? 'bg-primary/15 font-medium text-primary' : 'text-text-muted hover:bg-surface-muted' }}">{{ $child['label'] }}</a>
                            @endforeach
                        </div>
                    </div>
                    @continue
                @endif
                @php $active = request()->routeIs($item['match']); @endphp
                <a href="{{ route($item['route']) }}" wire:navigate @if ($active) aria-current="page" @endif
                   title="{{ $item['label'] }}"
                   class="flex min-h-11 items-center gap-3 rounded-lg px-3 text-sm font-medium transition {{ $active ? 'bg-primary/15 text-primary' : 'text-text-muted hover:bg-surface-muted' }}">
                    <x-icon :name="$item['icon']" />
                    <span x-show="!collapsed" class="truncate">{{ $item['label'] }}</span>
                    @if (($badges[$item['route']] ?? 0) > 0)
                        <span x-show="!collapsed" class="ml-auto rounded-full bg-primary px-2 text-[10px] font-bold leading-5 text-primary-fg"><span class="sr-only">{{ $badges[$item['route']] }} baru</span><span aria-hidden="true">{{ $badges[$item['route']] > 99 ? '99+' : $badges[$item['route']] }}</span></span>
                    @endif
                </a>
            @endforeach
        </nav>
        <button type="button" @click="collapsed = !collapsed" class="m-3 hidden min-h-11 items-center justify-center rounded-lg text-text-muted hover:bg-surface-muted lg:flex">
            <x-icon name="bars-3" /><span class="sr-only">Ciutkan menu</span>
        </button>
    </aside>

    <div class="transition-[padding]" :class="collapsed ? 'lg:pl-[4.5rem]' : 'lg:pl-64'">
        <header class="sticky top-0 z-10 flex h-16 items-center justify-between gap-3 border-b border-border bg-surface/90 px-4 backdrop-blur sm:px-6">
            <div class="flex min-w-0 items-center gap-3">
                <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary text-primary-fg lg:hidden"><x-icon name="academic-cap" /></span>
                <p class="truncate text-sm font-semibold">{{ $user->tenant?->name ?? 'Aksaraloka Academic' }}</p>
            </div>
            <div class="flex items-center gap-1">
                @if ($term)
                    <p class="mr-1 flex min-h-9 items-center gap-1.5 rounded-lg border border-border bg-surface-muted px-2.5 text-xs font-semibold tabular-nums sm:px-3 sm:text-sm"
                       title="Tahun ajaran {{ $term->name }} semester {{ $term->semester }} ({{ $term->starts_at->translatedFormat('j M Y') }} – {{ $term->ends_at->translatedFormat('j M Y') }})">
                        <x-icon name="calendar-days" class="size-4 text-primary" />
                        <span><span class="sr-only">Tahun ajaran </span>{{ $term->name }}<span class="hidden sm:inline"> · {{ $term->semester }}</span><span class="sr-only sm:hidden"> semester {{ $term->semester }}</span></span>
                    </p>
                @endif
                <x-theme-switcher />
                <div x-data="{ open: false }" @click.outside="open = false" class="relative">
                    <button type="button" @click="open = !open" class="flex min-h-11 items-center gap-2 rounded-lg px-2 hover:bg-surface-muted" aria-haspopup="menu" :aria-expanded="open">
                        <span class="flex size-9 items-center justify-center rounded-full bg-primary/15 text-sm font-semibold text-primary">{{ $user->initials() }}</span>
                        <span class="hidden text-left sm:block">
                            <span class="block max-w-40 truncate text-sm font-medium leading-tight">{{ $user->name }}</span>
                            <span class="block text-xs leading-tight text-text-muted">{{ $user->role->label() }}</span>
                        </span>
                    </button>
                    <div x-show="open" x-cloak role="menu" class="absolute right-0 z-30 mt-1 min-w-48 rounded-lg border border-border bg-surface p-1 shadow-sm">
                        <a href="{{ route('profile.show') }}" wire:navigate role="menuitem" class="flex min-h-10 w-full items-center gap-2 rounded-md px-3 text-sm hover:bg-surface-muted">
                            <x-icon name="user-circle" class="size-4" /> Profil
                        </a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" role="menuitem" class="flex min-h-10 w-full items-center gap-2 rounded-md px-3 text-sm text-danger hover:bg-surface-muted">
                                <x-icon name="arrow-right-start-on-rectangle" class="size-4" /> Keluar
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <main id="konten" class="mx-auto w-full max-w-6xl space-y-6 px-4 py-6 pb-28 sm:px-6 lg:pb-10">
            {{ $slot }}
        </main>
    </div>

    {{-- Bar navigasi bawah (HP) --}}
    <nav class="fixed inset-x-0 bottom-0 z-20 border-t border-border bg-surface lg:hidden" aria-label="Navigasi utama">
        <ul class="mx-auto flex max-w-xl">
            @foreach ($primaryItems as $item)
                @php $active = request()->routeIs($item['match']); @endphp
                <li class="min-w-0 flex-1">
                    <a href="{{ route($item['route']) }}" wire:navigate @if ($active) aria-current="page" @endif
                       class="flex min-h-14 flex-col items-center justify-center gap-0.5 px-1 text-xs font-medium {{ $active ? 'text-primary' : 'text-text-muted hover:bg-surface-muted' }}">
                        <x-icon :name="$item['icon']" />
                        <span class="max-w-full truncate">{{ $item['label'] }}</span>
                    </a>
                </li>
            @endforeach
            @if ($moreItems)
                <li class="min-w-0 flex-1">
                    <button type="button" @click="sheet = true" class="flex min-h-14 w-full flex-col items-center justify-center gap-0.5 px-1 text-xs font-medium text-text-muted hover:bg-surface-muted">
                        <x-icon name="bars-3" /> Lainnya
                    </button>
                </li>
            @endif
        </ul>
    </nav>

    @if ($moreItems)
        <div x-show="sheet" x-cloak class="fixed inset-0 z-40 flex items-end bg-black/50 lg:hidden" @click.self="sheet = false" role="dialog" aria-modal="true" aria-label="Menu lainnya">
            <div class="w-full rounded-t-2xl border border-border bg-surface p-5" x-trap.noscroll="sheet">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-base font-semibold">Menu lainnya</h2>
                    <button type="button" @click="sheet = false" class="inline-flex size-11 items-center justify-center rounded-lg hover:bg-surface-muted"><x-icon name="x-mark" /><span class="sr-only">Tutup</span></button>
                </div>
                <div class="space-y-1">
                    @foreach ($moreItems as $item)
                        @if (isset($item['group']) && ($moreItems[$loop->index - 1]['group'] ?? null) !== $item['group'])
                            <p class="flex items-center gap-2 px-3 pb-1 pt-3 text-xs font-semibold uppercase tracking-wide text-text-muted"><x-icon :name="$item['group_icon']" class="size-4" /> {{ $item['group'] }}</p>
                        @endif
                        <a href="{{ route($item['route']) }}" wire:navigate class="flex min-h-12 items-center gap-3 rounded-lg px-3 text-sm font-medium hover:bg-surface-muted {{ isset($item['group']) ? 'ml-4' : '' }}">
                            <x-icon :name="$item['icon']" class="text-primary" /> {{ $item['label'] }}
                            @if (($badges[$item['route']] ?? 0) > 0)<span class="ml-auto rounded-full bg-primary px-2 text-[10px] font-bold leading-5 text-primary-fg">{{ $badges[$item['route']] }}<span class="sr-only"> baru</span></span>@endif
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</div>

<div x-data="toaster" @toast.window="push($event.detail)" @if (session('toast')) x-init="push({ message: @js(session('toast')), type: 'success' })" @endif class="pointer-events-none fixed right-3 top-3 z-50 flex w-[min(22rem,calc(100vw-1.5rem))] flex-col gap-2" aria-live="polite">
    <template x-for="t in items" :key="t.id">
        <div class="pointer-events-auto flex items-start gap-2 rounded-lg border border-border bg-surface p-3 text-sm shadow-lg" role="status">
            <span class="mt-0.5 size-2 shrink-0 rounded-full" :class="{'bg-success': t.type==='success', 'bg-danger': t.type==='error', 'bg-warning': t.type==='warning', 'bg-info': t.type==='info'}"></span>
            <p class="flex-1" x-text="t.message"></p>
            <button type="button" @click="remove(t.id)" class="text-text-muted" aria-label="Tutup">✕</button>
        </div>
    </template>
</div>
</body>
</html>
