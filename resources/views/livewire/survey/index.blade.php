<div class="space-y-6">
    <x-page-header title="Kuesioner" subtitle="Daftar kuesioner untukmu dan pengelolaannya.">
        @if ($isAdmin)
            <x-slot:actions>
                <x-button :href="route('survey.create')" wire:navigate icon="plus">Buat kuesioner</x-button>
            </x-slot:actions>
        @endif
    </x-page-header>

    <x-card title="Untuk saya" :padding="false">
        @forelse ($mine as $survey)
            @php
                $answered = $answeredIds->contains($survey->id);
                $phase = $survey->phase();
            @endphp
            <div class="flex min-h-14 flex-wrap items-center gap-3 border-t border-border px-4 py-3 first:border-t-0" wire:key="mine-{{ $survey->id }}">
                <x-icon name="clipboard-document-list" class="text-primary" />
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium">{{ $survey->title }}</p>
                    <p class="truncate text-xs text-text-muted">
                        {{ $survey->category->label() }} · {{ $survey->opens_on->translatedFormat('j M') }}–{{ $survey->closes_on->translatedFormat('j M Y') }}@if ($survey->is_anonymous) · Anonim @endif
                    </p>
                </div>
                @if ($answered)
                    <x-badge variant="success" icon="check-circle">Sudah diisi</x-badge>
                @elseif ($phase === 'open')
                    <x-button :href="route('survey.fill', $survey)" wire:navigate size="sm">Isi sekarang</x-button>
                @elseif ($phase === 'upcoming')
                    <x-badge variant="info" icon="clock">Dibuka {{ $survey->opens_on->translatedFormat('j M') }}</x-badge>
                @else
                    <x-badge icon="x-circle">Ditutup</x-badge>
                @endif
            </div>
        @empty
            <x-empty-state icon="clipboard-document-list" title="Belum ada kuesioner untukmu" description="Kuesioner yang ditujukan kepadamu akan muncul di sini." />
        @endforelse
    </x-card>

    @if ($isManager)
        <x-card title="Semua kuesioner" :padding="false">
            @forelse ($all as $survey)
                <div class="flex min-h-14 flex-wrap items-center gap-3 border-t border-border px-4 py-3 first:border-t-0" wire:key="all-{{ $survey->id }}">
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium">{{ $survey->title }}</p>
                        <p class="truncate text-xs text-text-muted">
                            {{ $survey->category->label() }} · {{ $survey->questions_count }} pertanyaan · {{ $survey->responses_count }}/{{ $service->eligibleCount($survey) }} responden
                        </p>
                    </div>
                    <x-badge :variant="$survey->phaseVariant()">{{ $survey->phaseLabel() }}</x-badge>
                    <div class="flex flex-wrap gap-2">
                        <x-button size="sm" variant="secondary" :href="route('survey.results', $survey)" wire:navigate icon="chart-pie">Hasil</x-button>
                        @if ($isAdmin)
                            <x-button size="sm" variant="secondary" :href="route('survey.edit', $survey)" wire:navigate>Ubah</x-button>
                            <x-button size="sm" variant="secondary" wire:click="togglePublished({{ $survey->id }})">{{ $survey->is_published ? 'Sembunyikan' : 'Terbitkan' }}</x-button>
                            <x-button size="sm" variant="ghost" wire:click="delete({{ $survey->id }})" wire:confirm="Hapus kuesioner ini?">
                                <x-icon name="trash" class="size-4 text-danger" /><span class="sr-only">Hapus {{ $survey->title }}</span>
                            </x-button>
                        @endif
                    </div>
                </div>
            @empty
                <x-empty-state icon="clipboard-document-list" title="Belum ada kuesioner" :description="$isAdmin ? 'Buat kuesioner pertama untuk mulai mengumpulkan umpan balik.' : 'Kuesioner akan tampil di sini setelah dibuat admin.'">
                    @if ($isAdmin)<x-button :href="route('survey.create')" wire:navigate icon="plus">Buat kuesioner</x-button>@endif
                </x-empty-state>
            @endforelse
        </x-card>
    @endif
</div>
