<div class="space-y-6">
    <x-page-header title="Notifikasi" :subtitle="$unreadCount > 0 ? $unreadCount.' belum dibaca' : 'Semua sudah dibaca'">
        <x-slot:actions>
            <select wire:model.live="filter" class="min-h-11 rounded-lg border border-border bg-surface px-3 text-sm" aria-label="Filter notifikasi">
                <option value="all">Semua</option>
                <option value="unread">Belum dibaca</option>
            </select>
            @if ($unreadCount > 0)
                <button type="button" wire:click="markAllAsRead" class="inline-flex min-h-11 items-center rounded-lg border border-border px-4 text-sm font-medium hover:bg-surface-muted">Tandai semua dibaca</button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <x-card>
        <ul class="divide-y divide-border">
            @forelse ($notifications as $notification)
                <li wire:key="notif-{{ $notification->id }}">
                    <button type="button" wire:click="open('{{ $notification->id }}')" class="flex w-full items-start gap-3 px-2 py-3 text-left hover:bg-surface-muted">
                        <span class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-full {{ match ($notification->data['tone'] ?? 'info') { 'success' => 'bg-success/15 text-success', 'danger' => 'bg-danger/15 text-danger', 'warning' => 'bg-warning/15 text-warning', default => 'bg-info/15 text-info' } }}">
                            <x-icon :name="$notification->data['icon'] ?? 'bell'" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm {{ $notification->read_at ? '' : 'font-semibold' }}">{{ $notification->data['title'] ?? 'Notifikasi' }}</span>
                            <span class="mt-0.5 block text-sm text-text-muted">{{ $notification->data['body'] ?? '' }}</span>
                            <span class="mt-1 block text-xs text-text-muted">{{ $notification->created_at->translatedFormat('j M Y H:i') }} · {{ $notification->created_at->diffForHumans() }}</span>
                        </span>
                        @unless ($notification->read_at)
                            <span class="mt-2 size-2.5 shrink-0 rounded-full bg-primary"><span class="sr-only">Belum dibaca</span></span>
                        @endunless
                    </button>
                </li>
            @empty
                <li><x-empty-state icon="bell" title="Tidak ada notifikasi" :description="$filter === 'unread' ? 'Semua notifikasi sudah dibaca.' : 'Pemberitahuan terbaru akan muncul di sini.'" /></li>
            @endforelse
        </ul>
    </x-card>

    {{ $notifications->links() }}
</div>
