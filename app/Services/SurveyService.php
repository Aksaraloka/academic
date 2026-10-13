<?php

namespace App\Services;

use App\Enums\SurveyQuestionType;
use App\Models\Survey;
use App\Models\SurveyAnswer;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SurveyService
{
    /**
     * Penanda responden tanpa membuka identitas: survey anonim tidak menyimpan user_id,
     * hash ini hanya dipakai untuk mencegah pengisian ganda.
     */
    public function respondentHash(Survey $survey, User $user): string
    {
        return hash_hmac('sha256', "survey:{$survey->id}:user:{$user->id}", (string) config('app.key'));
    }

    public function hasResponded(Survey $survey, User $user): bool
    {
        return SurveyResponse::query()
            ->where('survey_id', $survey->id)
            ->where('respondent_hash', $this->respondentHash($survey, $user))
            ->exists();
    }

    public function canRespond(Survey $survey, User $user): bool
    {
        return $survey->isOpen() && $survey->targetsRole($user->role) && ! $this->hasResponded($survey, $user);
    }

    /**
     * Simpan jawaban satu responden. Setiap pengguna hanya boleh mengisi sekali.
     *
     * @param  array<int|string, mixed>  $answers  question_id => jawaban (string, atau array untuk pilihan ganda)
     */
    public function submit(Survey $survey, User $user, array $answers): SurveyResponse
    {
        if (! $survey->targetsRole($user->role)) {
            throw ValidationException::withMessages(['survey' => 'Kuesioner ini tidak ditujukan untuk perananmu.']);
        }

        if (! $survey->isOpen()) {
            throw ValidationException::withMessages(['survey' => 'Kuesioner ini sedang tidak dibuka.']);
        }

        if ($this->hasResponded($survey, $user)) {
            throw ValidationException::withMessages(['survey' => 'Kamu sudah mengisi kuesioner ini.']);
        }

        $clean = $this->validateAnswers($survey, $answers);

        try {
            return DB::transaction(function () use ($survey, $user, $clean): SurveyResponse {
                $response = SurveyResponse::create([
                    'survey_id' => $survey->id,
                    'user_id' => $survey->is_anonymous ? null : $user->id,
                    'respondent_hash' => $this->respondentHash($survey, $user),
                    'submitted_at' => now(),
                ]);

                foreach ($clean as $questionId => $value) {
                    SurveyAnswer::create([
                        'survey_response_id' => $response->id,
                        'survey_question_id' => $questionId,
                        'answer' => is_array($value) ? null : $value,
                        'choices' => is_array($value) ? $value : null,
                    ]);
                }

                return $response;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['survey' => 'Kamu sudah mengisi kuesioner ini.']);
        }
    }

    /**
     * @param  array<int|string, mixed>  $answers
     * @return array<int, string|array<int, string>> hanya pertanyaan yang dijawab
     */
    private function validateAnswers(Survey $survey, array $answers): array
    {
        $clean = [];
        $errors = [];

        foreach ($survey->questions as $question) {
            $raw = $answers[$question->id] ?? null;
            $key = "answers.{$question->id}";
            $isEmpty = $raw === null || $raw === '' || $raw === [];

            if ($isEmpty) {
                if ($question->is_required) {
                    $errors[$key] = 'Pertanyaan ini wajib dijawab.';
                }

                continue;
            }

            $value = match ($question->type) {
                SurveyQuestionType::Rating => $this->rating($raw),
                SurveyQuestionType::Single => $this->single($question, $raw),
                SurveyQuestionType::Multiple => $this->multiple($question, $raw),
                SurveyQuestionType::Text => $this->text($raw),
            };

            if ($value === null) {
                $errors[$key] = 'Jawaban tidak valid.';

                continue;
            }

            $clean[$question->id] = $value;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $clean;
    }

    private function rating(mixed $raw): ?string
    {
        return is_numeric($raw) && (int) $raw == $raw && $raw >= 1 && $raw <= 5 ? (string) (int) $raw : null;
    }

    private function single(SurveyQuestion $question, mixed $raw): ?string
    {
        return is_string($raw) && in_array($raw, $question->options ?? [], true) ? $raw : null;
    }

    /**
     * @return array<int, string>|null
     */
    private function multiple(SurveyQuestion $question, mixed $raw): ?array
    {
        if (! is_array($raw)) {
            return null;
        }

        $choices = array_values(array_unique(array_map('strval', $raw)));

        return $choices !== [] && array_diff($choices, $question->options ?? []) === [] ? $choices : null;
    }

    private function text(mixed $raw): ?string
    {
        return is_string($raw) && mb_strlen(trim($raw)) > 0 && mb_strlen($raw) <= 2000 ? trim($raw) : null;
    }

    /**
     * Jumlah pengguna yang berhak mengisi, sebagai pembagi tingkat respons.
     */
    public function eligibleCount(Survey $survey): int
    {
        return User::query()->where('tenant_id', $survey->tenant_id)->whereIn('role', $survey->audience ?? [])->count();
    }

    /**
     * Rekap hasil per pertanyaan: distribusi skala, hitungan pilihan, dan jawaban teks terbaru.
     *
     * @return Collection<int, array{question: SurveyQuestion, total: int, average: ?float, distribution: array<string, int>, texts: Collection<int, string>}>
     */
    public function results(Survey $survey): Collection
    {
        $answers = SurveyAnswer::query()
            ->whereIn('survey_question_id', $survey->questions->pluck('id'))
            ->latest('id')
            ->get()
            ->groupBy('survey_question_id');

        return $survey->questions->map(function (SurveyQuestion $question) use ($answers): array {
            $rows = $answers->get($question->id, collect());
            $distribution = [];
            $average = null;
            $texts = collect();

            switch ($question->type) {
                case SurveyQuestionType::Rating:
                    $distribution = array_fill_keys(['1', '2', '3', '4', '5'], 0);
                    foreach ($rows as $row) {
                        $distribution[$row->answer]++;
                    }
                    $average = $rows->isEmpty() ? null : round($rows->avg(fn ($row) => (int) $row->answer), 2);
                    break;
                case SurveyQuestionType::Single:
                case SurveyQuestionType::Multiple:
                    $distribution = array_fill_keys($question->options ?? [], 0);
                    foreach ($rows as $row) {
                        foreach ($row->choices ?? [$row->answer] as $choice) {
                            if (array_key_exists($choice, $distribution)) {
                                $distribution[$choice]++;
                            }
                        }
                    }
                    break;
                case SurveyQuestionType::Text:
                    $texts = $rows->pluck('answer')->filter()->values();
                    break;
            }

            return [
                'question' => $question,
                'total' => $rows->count(),
                'average' => $average,
                'distribution' => $distribution,
                'texts' => $texts,
            ];
        });
    }
}
