<div class="mx-auto max-w-2xl space-y-6">
    <div><x-button :href="route('evaluations.mine')" wire:navigate variant="ghost" size="sm" icon="arrow-left">Evaluasi dosen</x-button></div>

    <header class="space-y-2">
        <x-badge variant="primary">Evaluasi dosen</x-badge>
        <h1 class="text-2xl font-bold sm:text-3xl">{{ $assignment->subject->name }}</h1>
        <p class="text-base text-text-muted">{{ $assignment->teacher->name }} · Kelas {{ $assignment->classLabel() }}</p>
        <p class="text-sm text-text-muted">Jawabanmu tidak dikaitkan dengan namamu. Dosen hanya melihat rekap setelah minimal {{ \App\Services\EvaluationService::MIN_RESPONSES }} mahasiswa mengisi.</p>
    </header>

    @if ($answered)
        <x-card><x-empty-state icon="check-circle" title="Kamu sudah mengevaluasi kuliah ini" description="Terima kasih. Jawaban tidak bisa diubah." /></x-card>
    @elseif (! $period->isOpen())
        <x-card><x-empty-state icon="clock" title="Periode evaluasi sedang ditutup" :description="'Periode: '.$period->opens_on->translatedFormat('j M').' – '.$period->closes_on->translatedFormat('j F Y').'.'" /></x-card>
    @else
        @error('evaluation')<p class="rounded-lg border border-danger bg-danger/15 p-3 text-sm text-danger" role="alert">{{ $message }}</p>@enderror

        <form wire:submit="submit" class="space-y-4">
            @include('partials.survey-questions', ['survey' => $survey, 'answers' => $answers])

            <div class="flex flex-wrap gap-2">
                <x-button type="submit" icon="paper-airplane" wire:loading.attr="disabled" wire:target="submit">Kirim evaluasi</x-button>
                <x-button :href="route('evaluations.mine')" wire:navigate variant="secondary">Batal</x-button>
            </div>
        </form>
    @endif
</div>
