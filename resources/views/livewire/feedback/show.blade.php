<div class="mx-auto max-w-3xl space-y-6">
    <div>
        <x-button :href="route('feedback.index')" wire:navigate variant="ghost" size="sm" icon="arrow-left">Masukan dan komplain</x-button>
    </div>

    <header class="space-y-2">
        <div class="flex flex-wrap items-center gap-2">
            <x-badge :variant="$ticket->type->variant()" :icon="$ticket->type->icon()">{{ $ticket->type->label() }}</x-badge>
            <x-badge :variant="$ticket->status->variant()" :icon="$ticket->status->icon()">{{ $ticket->status->label() }}</x-badge>
            @if ($ticket->is_anonymous)<x-badge icon="eye-slash">Anonim</x-badge>@endif
            <span class="text-xs tabular-nums text-text-muted">{{ $ticket->number }}</span>
        </div>
        <h1 class="text-2xl font-bold sm:text-3xl">{{ $ticket->subject }}</h1>
        <p class="text-sm text-text-muted">
            {{ $ticket->category->label() }} · dari {{ $sender }} · {{ $ticket->created_at->translatedFormat('j F Y, H.i') }}
            @if ($handler) · ditangani {{ $handler->name }}@endif
        </p>
    </header>

    <x-card>
        <p class="whitespace-pre-line text-base leading-relaxed">{{ $ticket->body }}</p>
        @if ($ticket->attachment_path)
            <div class="mt-4">
                <x-button :href="route('feedback.attachment', $ticket)" target="_blank" rel="noopener" variant="secondary" size="sm" icon="paper-clip">Lihat lampiran</x-button>
            </div>
        @endif
    </x-card>

    @if ($isHandler)
        <x-card title="Penanganan">
            <form wire:submit="updateStatus" class="flex flex-wrap items-end gap-3">
                <div class="min-w-48 flex-1 sm:max-w-xs">
                    <x-field label="Status" name="status">
                        <select id="status" wire:model="status" class="input">
                            @foreach ($statuses as $option)<option value="{{ $option->value }}">{{ $option->label() }}</option>@endforeach
                        </select>
                    </x-field>
                </div>
                <x-button type="submit" variant="secondary" wire:loading.attr="disabled" wire:target="updateStatus">Simpan status</x-button>
            </form>
            @if ($ticket->resolved_at)
                <p class="mt-3 text-xs text-text-muted">Diselesaikan {{ $ticket->resolved_at->translatedFormat('j F Y, H.i') }}</p>
            @endif
        </x-card>
    @endif

    <section aria-labelledby="percakapan" class="space-y-3">
        <h2 id="percakapan" class="text-lg font-semibold">Percakapan</h2>

        @forelse ($replies as $reply)
            @php
                $byHandler = $reply->user && \App\Models\FeedbackTicket::isHandler($reply->user);
                $mine = $reply->user_id === auth()->id();
                $author = $byHandler ? $reply->user->name.' · '.$reply->user->role->label() : ($mine ? 'Kamu' : ($ticket->is_anonymous ? 'Anonim' : ($reply->user?->name ?? 'Pengguna dihapus')));
            @endphp
            <div wire:key="reply-{{ $reply->id }}" class="rounded-card border p-4 {{ $byHandler ? 'border-primary/40 bg-primary/5' : 'border-border bg-surface' }}">
                <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-text-muted">
                    <span class="font-semibold {{ $byHandler ? 'text-primary' : 'text-text' }}">{{ $author }}</span>
                    <span class="tabular-nums">{{ $reply->created_at->translatedFormat('j M Y, H.i') }}</span>
                </div>
                <p class="mt-2 whitespace-pre-line text-sm leading-relaxed">{{ $reply->body }}</p>
            </div>
        @empty
            <p class="rounded-card border border-border bg-surface p-4 text-sm text-text-muted">Belum ada balasan. {{ $isHandler ? 'Tanggapi agar status berubah menjadi diproses.' : 'Kami akan menanggapi secepatnya.' }}</p>
        @endforelse

        @if ($ticket->isOpen() && ($isHandler || $ticket->isOwnedBy(auth()->user())))
            <form wire:submit="reply" class="space-y-3">
                <x-field label="Balasan" name="body">
                    <textarea id="body" wire:model="body" rows="3" class="input" maxlength="3000" @error('body') aria-invalid="true" @enderror></textarea>
                </x-field>
                <x-button type="submit" icon="paper-airplane" wire:loading.attr="disabled" wire:target="reply">Kirim balasan</x-button>
            </form>
        @elseif (! $ticket->isOpen())
            <p class="text-sm text-text-muted" role="status">Tiket ini sudah ditutup.</p>
        @endif
    </section>
</div>
