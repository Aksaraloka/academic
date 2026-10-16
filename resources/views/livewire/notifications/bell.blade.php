<div x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false" class="relative" wire:poll.60s>
    <button type="button" @click="open = !open" class="relative flex size-11 items-center justify-center rounded-lg text-text-muted hover:bg-surface-muted" aria-haspopup="true" :aria-expanded="open">
        <x-icon name="bell" />
        @if ($unreadCount > 0)
            <span class="absolute right-1 top-1 min-w-4 rounded-full bg-primary px-1 text-center text-[10px] font-bold leading-4 text-primary-fg" aria-hidden="true">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>
        @endif
        <span class="sr-only">Notifikasi, {{ $unreadCount }} belum dibaca</span>
    </button>

    <div x-show="open" x-cloak x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
         class="fixed inset-x-3 top-16 z-30 origin-top-right rounded-lg border border-border bg-surface shadow-lg sm:absolute sm:inset-x-auto sm:right-0 sm:top-full sm:mt-1 sm:w-96">
        <div class="flex items-center justify-between gap-2 border-b border-border px-4 py-3">
            <h2 class="text-sm font-semibold">Notifikasi</h2>
            @if ($unreadCount > 0)
                <button type="button" wire:click="markAllAsRead" class="text-xs font-medium text-primary hover:underline">Tandai semua dibaca</button>
            @endif
        </div>

        <ul class="max-h-96 divide-y divide-border overflow-y-auto">
            @forelse ($notifications as $notification)
                <li wire:key="bell-{{ $notification->id }}">
                    <button type="button" wire:click="open('{{ $notification->id }}')" class="flex w-full items-start gap-3 px-4 py-3 text-left hover:bg-surface-muted {{ $notification->read_at ? '' : 'bg-primary/5' }}">
                        <span class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full {{ match ($notification->data['tone'] ?? 'info') { 'success' => 'bg-success/15 text-success', 'danger' => 'bg-danger/15 text-danger', 'warning' => 'bg-warning/15 text-warning', default => 'bg-info/15 text-info' } }}">
                            <x-icon :name="$notification->data['icon'] ?? 'bell'" class="size-4" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm {{ $notification->read_at ? '' : 'font-semibold' }}">{{ $notification->data['title'] ?? 'Notifikasi' }}</span>
                            <span class="mt-0.5 line-clamp-2 block text-xs text-text-muted">{{ $notification->data['body'] ?? '' }}</span>
                            <span class="mt-1 block text-[11px] text-text-muted">{{ $notification->created_at->diffForHumans() }}</span>
                        </span>
                        @unless ($notification->read_at)
                            <span class="mt-2 size-2 shrink-0 rounded-full bg-primary"><span class="sr-only">Belum dibaca</span></span>
                        @endunless
                    </button>
                </li>
            @empty
                <li><x-empty-state icon="bell" title="Belum ada notifikasi" description="Pemberitahuan terbaru akan muncul di sini." /></li>
            @endforelse
        </ul>

        <a href="{{ route('notifications.index') }}" wire:navigate @click="open = false" class="block border-t border-border px-4 py-3 text-center text-sm font-medium text-primary hover:bg-surface-muted">Lihat semua notifikasi</a>
    </div>
</div>
