<div class="space-y-6">
    <x-page-header title="Bimbingan akademik" subtitle="Catatan pertemuan dengan dosen wali." />

    <x-card>
        <p class="text-sm text-text-muted">Dosen wali</p>
        <p class="text-base font-semibold">{{ $advisor?->name ?? 'Belum ditetapkan' }}</p>
    </x-card>

    <x-card title="Riwayat bimbingan" :padding="false">
        @forelse ($notes as $note)
            <div class="border-t border-border px-4 py-3 first:border-t-0" wire:key="note-{{ $note->id }}">
                <div class="flex flex-wrap items-center gap-2">
                    <p class="flex-1 text-sm font-medium">{{ $note->topic }}</p>
                    <span class="text-xs text-text-muted">{{ $note->held_on->translatedFormat('j F Y') }} · {{ $note->teacher->name }}</span>
                </div>
                <p class="mt-1 whitespace-pre-line text-sm">{{ $note->notes }}</p>
                @if ($note->follow_up)<p class="mt-1 text-xs text-text-muted">Tindak lanjut: {{ $note->follow_up }}</p>@endif
            </div>
        @empty
            <x-empty-state icon="user-group" title="Belum ada catatan" description="Catatan muncul setelah dosen wali mencatat bimbingan denganmu." />
        @endforelse
    </x-card>
</div>
