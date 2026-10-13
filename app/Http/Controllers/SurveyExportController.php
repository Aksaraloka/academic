<?php

namespace App\Http\Controllers;

use App\Models\Survey;
use App\Models\SurveyAnswer;
use App\Models\User;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SurveyExportController extends Controller
{
    /**
     * Ekspor jawaban survey sebagai CSV. Survey anonim tidak memuat identitas responden.
     */
    public function __invoke(Survey $survey): StreamedResponse
    {
        abort_unless(Survey::isManager(auth()->user()), 403);

        $survey->load('questions');
        $responses = $survey->responses()->with('answers')->orderBy('submitted_at')->get();
        $names = $survey->is_anonymous
            ? collect()
            : User::query()->whereIn('id', $responses->pluck('user_id')->filter())->pluck('name', 'id');

        return response()->streamDownload(function () use ($survey, $responses, $names): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, array_merge(
                ['Waktu kirim'],
                $survey->is_anonymous ? [] : ['Responden'],
                $survey->questions->pluck('text')->all(),
            ));

            foreach ($responses as $response) {
                $byQuestion = $response->answers->keyBy('survey_question_id');

                fputcsv($out, array_merge(
                    [$response->submitted_at->format('Y-m-d H:i')],
                    $survey->is_anonymous ? [] : [$names[$response->user_id] ?? ''],
                    $survey->questions->map(function ($question) use ($byQuestion): string {
                        /** @var SurveyAnswer|null $answer */
                        $answer = $byQuestion->get($question->id);

                        return $answer === null ? '' : (is_array($answer->choices) ? implode('; ', $answer->choices) : (string) $answer->answer);
                    })->all(),
                ));
            }

            fclose($out);
        }, 'survey-'.str($survey->title)->slug().'.csv', ['Content-Type' => 'text/csv']);
    }
}
