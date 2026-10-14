<?php

namespace App\Services;

use App\Enums\SurveyQuestionType;
use App\Models\AcademicTerm;
use App\Models\EvaluationPeriod;
use App\Models\Student;
use App\Models\Survey;
use App\Models\SurveyAnswer;
use App\Models\SurveyResponse;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * EDOM (evaluasi dosen oleh mahasiswa): satu kuesioner dipakai untuk semua kuliah semester itu,
 * jawaban anonim per kelas mata kuliah, dan bisa menjadi syarat melihat nilai.
 */
class EvaluationService
{
    /** Jumlah responden minimal agar hasil per kelas ditampilkan (menjaga anonimitas). */
    public const MIN_RESPONSES = 3;

    public function __construct(
        private readonly SurveyService $surveys,
        private readonly ScheduleBoard $board,
    ) {}

    public function periodFor(AcademicTerm $term): ?EvaluationPeriod
    {
        return EvaluationPeriod::query()->with('survey.questions')->where('academic_term_id', $term->id)->where('is_published', true)->first();
    }

    public function respondentHash(Survey $survey, TeachingAssignment $assignment, User $user): string
    {
        return hash_hmac('sha256', "edom:{$survey->id}:assignment:{$assignment->id}:user:{$user->id}", (string) config('app.key'));
    }

    /**
     * Kuliah yang diikuti mahasiswa pada semester itu.
     *
     * @return Collection<int, TeachingAssignment>
     */
    public function assignmentsFor(Student $student, AcademicTerm $term): Collection
    {
        return TeachingAssignment::query()
            ->with(['subject', 'teacher', 'studyGroup'])
            ->whereIn('id', $this->board->assignmentIdsForStudent($student->id, $term))
            ->get()
            ->sortBy(fn (TeachingAssignment $assignment): string => $assignment->subject->name)
            ->values();
    }

    /**
     * Id kuliah yang sudah dievaluasi mahasiswa itu pada periode tersebut.
     *
     * @param  Collection<int, TeachingAssignment>  $assignments
     * @return Collection<int, int>
     */
    public function completedIds(Student $student, EvaluationPeriod $period, Collection $assignments): Collection
    {
        if ($student->user === null) {
            return collect();
        }

        $hashes = $assignments->mapWithKeys(fn (TeachingAssignment $assignment): array => [
            $this->respondentHash($period->survey, $assignment, $student->user) => $assignment->id,
        ]);

        return SurveyResponse::query()
            ->where('survey_id', $period->survey_id)->whereIn('respondent_hash', $hashes->keys())
            ->pluck('respondent_hash')
            ->map(fn (string $hash): int => $hashes[$hash]);
    }

    /**
     * Apakah nilai semester itu tertahan karena EDOM belum lengkap.
     *
     * @return array{blocked: bool, pending: Collection<int, TeachingAssignment>, period: ?EvaluationPeriod}
     */
    public function gradeGate(Student $student, AcademicTerm $term): array
    {
        $period = $this->periodFor($term);

        if ($period === null || ! $period->gates_grades || $student->user === null) {
            return ['blocked' => false, 'pending' => collect(), 'period' => $period];
        }

        $assignments = $this->assignmentsFor($student, $term);
        $done = $this->completedIds($student, $period, $assignments);
        $pending = $assignments->reject(fn (TeachingAssignment $assignment): bool => $done->contains($assignment->id))->values();

        return ['blocked' => $pending->isNotEmpty(), 'pending' => $pending, 'period' => $period];
    }

    /**
     * @param  array<int|string, mixed>  $answers
     */
    public function submit(EvaluationPeriod $period, TeachingAssignment $assignment, User $user, array $answers): SurveyResponse
    {
        $student = $user->student;

        if ($student === null || ! $this->assignmentsFor($student, $period->academicTerm)->contains('id', $assignment->id)) {
            throw ValidationException::withMessages(['evaluation' => 'Kamu tidak mengikuti kuliah ini.']);
        }

        if (! $period->isOpen()) {
            throw ValidationException::withMessages(['evaluation' => 'Periode evaluasi dosen sedang tidak dibuka.']);
        }

        $survey = $period->survey->loadMissing('questions');
        $hash = $this->respondentHash($survey, $assignment, $user);

        if (SurveyResponse::query()->where('survey_id', $survey->id)->where('respondent_hash', $hash)->exists()) {
            throw ValidationException::withMessages(['evaluation' => 'Kamu sudah mengevaluasi kuliah ini.']);
        }

        $clean = $this->surveys->validateAnswers($survey, $answers);

        try {
            return DB::transaction(function () use ($survey, $assignment, $hash, $clean): SurveyResponse {
                $response = SurveyResponse::create([
                    'survey_id' => $survey->id, 'user_id' => null, 'teaching_assignment_id' => $assignment->id,
                    'respondent_hash' => $hash, 'submitted_at' => now(),
                ]);

                foreach ($clean as $questionId => $value) {
                    SurveyAnswer::create([
                        'survey_response_id' => $response->id, 'survey_question_id' => $questionId,
                        'answer' => is_array($value) ? null : $value, 'choices' => is_array($value) ? $value : null,
                    ]);
                }

                return $response;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['evaluation' => 'Kamu sudah mengevaluasi kuliah ini.']);
        }
    }

    /**
     * Rekap evaluasi satu kuliah. Rata-rata dan komentar baru tampil setelah MIN_RESPONSES responden.
     *
     * @return array{responses: int, visible: bool, ratings: Collection<int, array{text: string, average: float}>, average: ?float, comments: Collection<int, string>}
     */
    public function summary(EvaluationPeriod $period, TeachingAssignment $assignment): array
    {
        $survey = $period->survey->loadMissing('questions');
        $responses = SurveyResponse::query()->with('answers')->where('survey_id', $survey->id)->where('teaching_assignment_id', $assignment->id)->get();
        $visible = $responses->count() >= self::MIN_RESPONSES;

        $answers = $responses->flatMap->answers;

        $ratings = $survey->questions->where('type', SurveyQuestionType::Rating)->map(function ($question) use ($answers): array {
            $values = $answers->where('survey_question_id', $question->id)->pluck('answer')->filter()->map(fn ($value): int => (int) $value);

            return ['text' => $question->text, 'average' => $values->isEmpty() ? 0.0 : round($values->avg(), 2)];
        })->values();

        $comments = $survey->questions->where('type', SurveyQuestionType::Text)->flatMap(
            fn ($question) => $answers->where('survey_question_id', $question->id)->pluck('answer')->filter(),
        )->values();

        return [
            'responses' => $responses->count(),
            'visible' => $visible,
            'ratings' => $visible ? $ratings : collect(),
            'average' => $visible && $ratings->isNotEmpty() ? round($ratings->avg('average'), 2) : null,
            'comments' => $visible ? $comments : collect(),
        ];
    }
}
