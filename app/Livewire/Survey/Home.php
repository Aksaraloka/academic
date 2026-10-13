<?php

namespace App\Livewire\Survey;

use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Services\SurveyService;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Beranda survey')]
class Home extends Component
{
    public function render(SurveyService $service)
    {
        $user = auth()->user();

        $mine = Survey::query()->forAudience($user)->orderBy('closes_on')->get();
        $answeredIds = SurveyResponse::query()
            ->whereIn('survey_id', $mine->pluck('id'))
            ->whereIn('respondent_hash', $mine->map(fn (Survey $survey) => $service->respondentHash($survey, $user)))
            ->pluck('survey_id');

        $pending = $mine->filter(fn (Survey $survey): bool => $survey->isOpen() && ! $answeredIds->contains($survey->id))->values();

        $data = [
            'pending' => $pending,
            'answeredCount' => $mine->whereIn('id', $answeredIds)->count(),
            'openCount' => $mine->filter->isOpen()->count(),
            'isManager' => Survey::isManager($user),
        ];

        if ($data['isManager']) {
            $all = Survey::query()->withCount('responses')->orderByDesc('opens_on')->get();
            $active = $all->filter(fn (Survey $survey): bool => $survey->isOpen());

            $data += [
                'activeSurveys' => $active->map(fn (Survey $survey): array => [
                    'survey' => $survey,
                    'eligible' => $service->eligibleCount($survey),
                ]),
                'draftCount' => $all->where('is_published', false)->count(),
                'totalResponses' => $all->sum('responses_count'),
                'totalSurveys' => $all->count(),
            ];
        }

        return view('livewire.survey.home', $data);
    }
}
