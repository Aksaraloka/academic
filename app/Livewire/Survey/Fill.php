<?php

namespace App\Livewire\Survey;

use App\Enums\SurveyQuestionType;
use App\Models\Survey;
use App\Services\SurveyService;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Isi kuesioner')]
class Fill extends Component
{
    public Survey $survey;

    /** @var array<int|string, mixed> question_id => jawaban */
    public array $answers = [];

    public function mount(Survey $survey): void
    {
        abort_unless(
            $survey->is_published && $survey->targetsRole(auth()->user()->role),
            404,
        );

        $this->survey = $survey->load('questions');

        foreach ($this->survey->questions as $question) {
            $this->answers[$question->id] = $question->type === SurveyQuestionType::Multiple ? [] : '';
        }
    }

    public function submit(SurveyService $service): mixed
    {
        $service->submit($this->survey, auth()->user(), $this->answers);

        session()->flash('toast', 'Terima kasih! Jawabanmu sudah terkirim.');

        return $this->redirectRoute('survey.index', navigate: true);
    }

    public function render(SurveyService $service)
    {
        $user = auth()->user();

        return view('livewire.survey.fill', [
            'answered' => $service->hasResponded($this->survey, $user),
            'open' => $this->survey->isOpen(),
        ]);
    }
}
