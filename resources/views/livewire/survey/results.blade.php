<div class="space-y-6">
    <x-page-header :title="'Hasil · '.$survey->title" :subtitle="$survey->category->label().' · '.$survey->opens_on->translatedFormat('j M').'–'.$survey->closes_on->translatedFormat('j M Y')">
        <x-slot:actions>
            <x-button :href="route('survey.index')" wire:navigate variant="secondary" icon="arrow-left">Kuesioner</x-button>
            <x-button :href="route('survey.export', $survey)" icon="arrow-down-tray">Ekspor CSV</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
        <x-stat-tile label="Responden" :value="$responses" :note="'dari '.$eligible.' yang berhak'" />
        <x-stat-tile label="Tingkat respons" :value="$eligible > 0 ? round($responses / $eligible * 100).'%' : '–'" />
        <x-stat-tile class="col-span-2 sm:col-span-1" label="Status" :value="$survey->phaseLabel()" :note="$survey->is_anonymous ? 'Jawaban anonim' : 'Jawaban tercatat atas nama'" />
    </div>

    @if ($responses === 0)
        <x-card><x-empty-state icon="chart-pie" title="Belum ada jawaban" description="Hasil akan muncul setelah responden mengisi kuesioner." /></x-card>
    @else
        @foreach ($results as $row)
            @php $question = $row['question']; @endphp
            <x-card wire:key="res-{{ $question->id }}">
                <h2 class="text-base font-semibold"><span class="tabular-nums text-text-muted">{{ $loop->iteration }}.</span> {{ $question->text }}</h2>
                <p class="mb-3 mt-1 text-xs text-text-muted">{{ $question->type->label() }} · {{ $row['total'] }} jawaban</p>

                @if ($question->type === \App\Enums\SurveyQuestionType::Rating)
                    <p class="mb-3 flex items-baseline gap-2">
                        <span class="text-3xl font-bold tabular-nums">{{ $row['average'] !== null ? number_format($row['average'], 2, ',', '.') : '–' }}</span>
                        <span class="text-sm text-text-muted">rata-rata dari 5</span>
                    </p>
                @endif

                @if ($question->type === \App\Enums\SurveyQuestionType::Text)
                    @forelse ($row['texts']->take(30) as $text)
                        <p class="border-t border-border py-2 text-sm first:border-t-0">{{ $text }}</p>
                    @empty
                        <p class="text-sm text-text-muted">Belum ada jawaban.</p>
                    @endforelse
                @else
                    <div class="space-y-3">
                        @foreach ($row['distribution'] as $label => $count)
                            <x-progress :label="$question->type === \App\Enums\SurveyQuestionType::Rating ? 'Skor '.$label : (string) $label" :value="$count" :max="$row['total']" />
                        @endforeach
                    </div>
                @endif
            </x-card>
        @endforeach
    @endif
</div>
