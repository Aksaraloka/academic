<div class="space-y-6">
    <x-page-header title="Beranda survey" subtitle="Suaramu membantu kampus meningkatkan layanan dan mutu pembelajaran.">
        <x-slot:actions>
            <x-button :href="route('survey.index')" wire:navigate icon="clipboard-document-list">Lihat kuesioner</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
        <x-stat-tile label="Kuesioner dibuka" :value="$openCount" />
        <x-stat-tile label="Belum diisi" :value="$pending->count()" :tone="$pending->isNotEmpty() ? 'text-warning' : 'text-success'" :note="$pending->isNotEmpty() ? 'Mohon segera diisi' : 'Semua sudah terisi'" />
        <x-stat-tile class="col-span-2 sm:col-span-1" label="Sudah diisi" :value="$answeredCount" tone="text-success" />
    </div>

    <x-card title="Perlu kamu isi" :padding="false">
        @forelse ($pending as $survey)
            @php $daysLeft = (int) today()->diffInDays($survey->closes_on, false); @endphp
            <a href="{{ route('survey.fill', $survey) }}" wire:navigate wire:key="pend-{{ $survey->id }}"
               class="flex min-h-14 items-center gap-3 border-t border-border px-4 py-2.5 first:border-t-0 hover:bg-surface-muted">
                <x-icon name="clipboard-document-list" class="text-primary" />
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium">{{ $survey->title }}</p>
                    <p class="truncate text-xs {{ $daysLeft <= 3 ? 'font-semibold text-warning' : 'text-text-muted' }}">
                        Batas {{ $survey->closes_on->translatedFormat('j F Y') }}{{ $daysLeft <= 3 ? ' · '.($daysLeft <= 0 ? 'hari ini' : $daysLeft.' hari lagi') : '' }}
                    </p>
                </div>
                <x-badge :variant="$survey->category->variant()" class="hidden sm:inline-flex">{{ $survey->category->label() }}</x-badge>
                <x-badge variant="warning" icon="clock">Belum diisi</x-badge>
            </a>
        @empty
            <x-empty-state icon="check-circle" title="Tidak ada kuesioner yang perlu diisi" description="Terima kasih. Kuesioner baru akan muncul di sini saat dibuka." />
        @endforelse
    </x-card>

    @if ($isManager)
        <section aria-labelledby="pantau" class="space-y-4">
            <h2 id="pantau" class="text-lg font-semibold">Pemantauan kuesioner</h2>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                <x-stat-tile label="Kuesioner aktif" :value="$activeSurveys->count()" />
                <x-stat-tile label="Draf" :value="$draftCount" :tone="$draftCount > 0 ? 'text-warning' : 'text-text-muted'" />
                <x-stat-tile class="col-span-2 sm:col-span-1" label="Total jawaban masuk" :value="$totalResponses" :note="'dari '.$totalSurveys.' kuesioner'" />
            </div>

            <x-card title="Tingkat respons kuesioner aktif">
                @forelse ($activeSurveys as $row)
                    <div class="mb-4 last:mb-0" wire:key="act-{{ $row['survey']->id }}">
                        <div class="mb-1 flex items-center justify-between gap-3">
                            <a href="{{ route('survey.results', $row['survey']) }}" wire:navigate class="min-w-0 truncate text-sm font-medium hover:text-primary">{{ $row['survey']->title }}</a>
                            <span class="shrink-0 text-xs text-text-muted">s.d. {{ $row['survey']->closes_on->translatedFormat('j M') }}</span>
                        </div>
                        <x-progress label="Responden" :value="$row['survey']->responses_count" :max="$row['eligible']" empty="Belum ada responden yang berhak" />
                    </div>
                @empty
                    <p class="text-sm text-text-muted">Belum ada kuesioner yang sedang dibuka.</p>
                @endforelse
            </x-card>
        </section>
    @endif
</div>
