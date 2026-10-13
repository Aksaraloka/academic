<div class="space-y-6">
    <x-page-header title="Masukan dan komplain" :subtitle="$isHandler ? 'Tanggapi masukan dan komplain dari sivitas akademika.' : 'Sampaikan saran atau keluhanmu. Kami baca setiap pesan.'">
        <x-slot:actions>
            <x-button :href="route('feedback.create')" wire:navigate icon="plus">Kirim masukan</x-button>
        </x-slot:actions>
    </x-page-header>

    @if ($isHandler)
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach ($statuses as $status)
                <x-stat-tile :label="$status->label()" :value="$counts[$status->value] ?? 0"
                    :tone="match ($status->value) { 'baru' => 'text-info', 'diproses' => 'text-warning', 'selesai' => 'text-success', default => 'text-text-muted' }"
                    :note="$status->value === 'baru' && ($counts['baru'] ?? 0) > 0 ? 'Perlu ditanggapi' : null" />
            @endforeach
        </div>
    @endif

    <div class="grid gap-3 sm:grid-cols-2 sm:max-w-xl">
        <div>
            <label for="filter-status" class="sr-only">Status</label>
            <select id="filter-status" wire:model.live="status" class="input">
                <option value="">Semua status</option>
                @foreach ($statuses as $option)<option value="{{ $option->value }}">{{ $option->label() }}</option>@endforeach
            </select>
        </div>
        <div>
            <label for="filter-jenis" class="sr-only">Jenis</label>
            <select id="filter-jenis" wire:model.live="type" class="input">
                <option value="">Masukan dan komplain</option>
                @foreach ($types as $option)<option value="{{ $option->value }}">{{ $option->label() }}</option>@endforeach
            </select>
        </div>
    </div>

    <x-card :padding="false">
        @forelse ($tickets as $ticket)
            <a href="{{ route('feedback.show', $ticket) }}" wire:navigate wire:key="ticket-{{ $ticket->id }}"
               class="flex min-h-16 items-center gap-3 border-t border-border px-4 py-3 first:border-t-0 hover:bg-surface-muted">
                <x-icon :name="$ticket->type->icon()" class="shrink-0 {{ $ticket->type === \App\Enums\FeedbackType::Complaint ? 'text-danger' : 'text-primary' }}" />
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium">{{ $ticket->subject }}</p>
                    <p class="truncate text-xs text-text-muted">
                        <span class="tabular-nums">{{ $ticket->number }}</span>
                        · {{ $ticket->category->label() }}
                        @if ($isHandler) · {{ $ticket->senderLabelFor(auth()->user()) }}@endif
                        · {{ $ticket->updated_at->diffForHumans() }}
                    </p>
                </div>
                @if ($ticket->replies->isNotEmpty())
                    <span class="hidden items-center gap-1 text-xs text-text-muted sm:inline-flex"><x-icon name="chat-bubble-left-right" solid /> {{ $ticket->replies->count() }}</span>
                @endif
                <x-badge :variant="$ticket->type->variant()" class="hidden sm:inline-flex">{{ $ticket->type->label() }}</x-badge>
                <x-badge :variant="$ticket->status->variant()" :icon="$ticket->status->icon()">{{ $ticket->status->label() }}</x-badge>
            </a>
        @empty
            <x-empty-state icon="chat-bubble-left-right"
                :title="$filtered ? 'Tidak ada yang cocok' : ($isHandler ? 'Belum ada masukan atau komplain' : 'Kamu belum mengirim apa pun')"
                :description="$filtered ? 'Coba ubah filter status atau jenis.' : ($isHandler ? 'Pesan baru dari sivitas akademika akan muncul di sini.' : 'Ada saran untuk kampus atau keluhan layanan? Sampaikan di sini, boleh secara anonim.')">
                @unless ($isHandler || $filtered)
                    <x-button :href="route('feedback.create')" wire:navigate icon="plus">Kirim masukan</x-button>
                @endunless
            </x-empty-state>
        @endforelse
    </x-card>

    {{ $tickets->links() }}
</div>
