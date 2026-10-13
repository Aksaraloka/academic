<?php

namespace App\Livewire\Survey;

use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Services\SurveyService;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Kuesioner')]
class Index extends Component
{
    public function togglePublished(int $surveyId): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $survey = Survey::query()->findOrFail($surveyId);

        if (! $survey->is_published && $survey->questions()->doesntExist()) {
            $this->dispatch('toast', message: 'Tambahkan pertanyaan sebelum menerbitkan.', type: 'warning');

            return;
        }

        $survey->update(['is_published' => ! $survey->is_published]);
    }

    public function delete(int $surveyId): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $survey = Survey::query()->withCount('responses')->findOrFail($surveyId);

        if ($survey->responses_count > 0) {
            $this->dispatch('toast', message: 'Kuesioner yang sudah terisi tidak bisa dihapus. Sembunyikan saja.', type: 'warning');

            return;
        }

        $survey->delete();
        $this->dispatch('toast', message: 'Kuesioner dihapus.', type: 'success');
    }

    public function render(SurveyService $service)
    {
        $user = auth()->user();

        $mine = Survey::query()->forAudience($user)->orderBy('closes_on')->get();
        $answeredIds = SurveyResponse::query()
            ->whereIn('survey_id', $mine->pluck('id'))
            ->whereIn('respondent_hash', $mine->map(fn (Survey $survey) => $service->respondentHash($survey, $user)))
            ->pluck('survey_id');

        $isManager = Survey::isManager($user);

        return view('livewire.survey.index', [
            'mine' => $mine,
            'answeredIds' => $answeredIds,
            'isManager' => $isManager,
            'isAdmin' => $user->isAdmin(),
            'all' => $isManager
                ? Survey::query()->withCount(['responses', 'questions'])->orderByDesc('opens_on')->get()
                : collect(),
            'service' => $service,
        ]);
    }
}
