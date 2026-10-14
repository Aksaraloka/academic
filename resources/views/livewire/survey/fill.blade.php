<div class="space-y-6">
    <div>
        <x-button :href="route('survey.index')" wire:navigate variant="ghost" size="sm" icon="arrow-left">Kuesioner</x-button>
    </div>

    <header class="space-y-2">
        <div class="flex flex-wrap items-center gap-2">
            <x-badge :variant="$survey->category->variant()">{{ $survey->category->label() }}</x-badge>
            @if ($survey->is_anonymous)<x-badge icon="eye-slash">Anonim</x-badge>@endif
            <span class="text-xs text-text-muted">Batas {{ $survey->closes_on->translatedFormat('j F Y') }}</span>
        </div>
        <h1 class="text-2xl font-bold sm:text-3xl">{{ $survey->title }}</h1>
        @if ($survey->description)<p class="text-base text-text-muted">{{ $survey->description }}</p>@endif
        @if ($survey->is_anonymous)
            <p class="text-sm text-text-muted">Jawabanmu tidak dikaitkan dengan namamu. Sistem hanya mencatat bahwa kamu sudah mengisi.</p>
        @endif
    </header>

    @if ($answered)
        <x-card><x-empty-state icon="check-circle" title="Kamu sudah mengisi kuesioner ini" description="Terima kasih atas partisipasimu. Jawaban tidak bisa diubah." /></x-card>
    @elseif (! $open)
        <x-card><x-empty-state icon="clock" :title="$survey->phase() === 'upcoming' ? 'Kuesioner belum dibuka' : 'Kuesioner sudah ditutup'" :description="$survey->phase() === 'upcoming' ? 'Dibuka pada '.$survey->opens_on->translatedFormat('j F Y').'.' : 'Batas pengisian adalah '.$survey->closes_on->translatedFormat('j F Y').'.'" /></x-card>
    @else
        @error('survey')
            <p class="rounded-lg border border-danger bg-danger/15 p-3 text-sm text-danger" role="alert">{{ $message }}</p>
        @enderror

        <form wire:submit="submit" class="space-y-4">
            @include('partials.survey-questions', ['survey' => $survey, 'answers' => $answers])

            <div class="flex flex-wrap gap-2">
                <x-button type="submit" icon="paper-airplane" wire:loading.attr="disabled" wire:target="submit">Kirim jawaban</x-button>
                <x-button :href="route('survey.index')" wire:navigate variant="secondary">Batal</x-button>
            </div>
        </form>
    @endif
</div>
