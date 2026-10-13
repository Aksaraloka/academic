<?php

namespace App\Livewire\Survey;

use App\Models\Survey;
use App\Services\SurveyService;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Hasil survey')]
class Results extends Component
{
    public Survey $survey;

    public function mount(Survey $survey): void
    {
        abort_unless(Survey::isManager(auth()->user()), 403);

        $this->survey = $survey->load('questions');
    }

    public function render(SurveyService $service)
    {
        $responses = $this->survey->responses()->count();
        $eligible = $service->eligibleCount($this->survey);

        return view('livewire.survey.results', [
            'results' => $service->results($this->survey),
            'responses' => $responses,
            'eligible' => $eligible,
        ]);
    }
}
