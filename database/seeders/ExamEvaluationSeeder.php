<?php

namespace Database\Seeders;

use App\Enums\AcademicEventType;
use App\Enums\ExamType;
use App\Enums\SurveyCategory;
use App\Enums\SurveyQuestionType;
use App\Models\AcademicEvent;
use App\Models\AcademicTerm;
use App\Models\EvaluationPeriod;
use App\Models\ExamSchedule;
use App\Models\Student;
use App\Models\StudyGroupMember;
use App\Models\Survey;
use App\Models\SurveyAnswer;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\TeachingAssignment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Jadwal UTS dan UAS semester berjalan serta data EDOM (evaluasi dosen): kuesioner, periode semester berjalan dan semester lalu,
 * dan jawaban anonim. Dipanggil dari DatabaseSeeder setelah KRS dan data akademik dibuat.
 */
class ExamEvaluationSeeder extends Seeder
{
    public function seed(Tenant $tenant, User $actor): void
    {
        $current = AcademicTerm::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('is_active', true)->first();
        $previous = AcademicTerm::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('is_active', false)->orderByDesc('starts_at')->first();

        if ($current) {
            $this->exams($tenant, $current);
        }

        $survey = $this->survey($tenant, $actor);

        if ($previous) {
            $period = EvaluationPeriod::create([
                'tenant_id' => $tenant->id, 'academic_term_id' => $previous->id, 'survey_id' => $survey->id,
                'opens_on' => $previous->ends_at->copy()->subDays(14), 'closes_on' => $previous->ends_at->copy()->addDays(7), 'gates_grades' => true, 'is_published' => true,
            ]);
            $this->responses($tenant, $period, $survey, 85);
        }

        if ($current) {
            $period = EvaluationPeriod::create([
                'tenant_id' => $tenant->id, 'academic_term_id' => $current->id, 'survey_id' => $survey->id,
                'opens_on' => today()->subDays(3), 'closes_on' => today()->addDays(18), 'gates_grades' => true, 'is_published' => true,
            ]);
            $this->responses($tenant, $period, $survey, 35);
        }
    }

    private function exams(Tenant $tenant, AcademicTerm $term): void
    {
        $agenda = fn (AcademicEventType $type) => AcademicEvent::query()->withoutGlobalScopes()->where('academic_term_id', $term->id)->where('type', $type)->first();
        $assignments = TeachingAssignment::query()->withoutGlobalScopes()->with('subject')->where('academic_term_id', $term->id)->orderBy('study_group_id')->orderBy('id')->get()->groupBy('study_group_id');

        foreach ([ExamType::Midterm, ExamType::Final] as $type) {
            $event = $agenda($type->eventType());

            if ($event === null) {
                continue;
            }

            $days = collect(range(0, 5))->map(fn (int $offset) => $event->starts_on->copy()->addDays($offset))->filter(fn ($date) => ! $date->isSunday())->values();

            foreach ($assignments as $groupAssignments) {
                foreach ($groupAssignments->values() as $index => $assignment) {
                    $slot = intdiv($index, $days->count());

                    ExamSchedule::create([
                        'tenant_id' => $tenant->id, 'academic_term_id' => $term->id, 'teaching_assignment_id' => $assignment->id, 'type' => $type,
                        'date' => $days[$index % $days->count()], 'starts_at' => ['08:00', '10:00', '13:00'][$slot % 3], 'ends_at' => ['09:40', '11:40', '14:40'][$slot % 3],
                        'notes' => $type === ExamType::Final ? 'Bawa kartu ujian dan kartu identitas' : null,
                    ]);
                }
            }
        }
    }

    private function survey(Tenant $tenant, User $actor): Survey
    {
        $survey = Survey::create([
            'tenant_id' => $tenant->id, 'created_by' => $actor->id, 'category' => SurveyCategory::LecturerEvaluation, 'title' => 'Kuesioner EDOM (Evaluasi Dosen oleh Mahasiswa)',
            'description' => 'Penilaian proses pembelajaran per mata kuliah. Jawaban anonim dan hanya dibaca dalam bentuk rekap.',
            'audience' => ['student'], 'opens_on' => today()->subYear(), 'closes_on' => today()->addYear(), 'is_anonymous' => true, 'is_published' => false,
        ]);

        foreach ([
            ['rating', 'Dosen menjelaskan materi dengan jelas'],
            ['rating', 'Dosen hadir dan mulai kuliah tepat waktu'],
            ['rating', 'Materi sesuai dengan rencana pembelajaran (RPS)'],
            ['rating', 'Dosen terbuka terhadap pertanyaan dan diskusi'],
            ['rating', 'Penilaian tugas dan ujian dilakukan adil'],
            ['teks', 'Saran untuk dosen pengampu'],
        ] as $position => [$type, $text]) {
            $survey->questions()->create(['position' => $position, 'type' => $type, 'text' => $text, 'options' => null, 'is_required' => $type === 'rating']);
        }

        return $survey->load('questions');
    }

    /**
     * Jawaban anonim: mahasiswa tanpa akun memakai penanda semu agar rekap per kelas terisi; mahasiswa contoh hanya
     * dianggap lengkap pada periode yang sudah ditutup.
     */
    private function responses(Tenant $tenant, EvaluationPeriod $period, Survey $survey, int $chance): void
    {
        $demo = Student::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->orderBy('id')->first();
        $comments = ['Penjelasan sudah baik, mohon lebih banyak contoh soal.', 'Sangat membantu, pertahankan.', 'Mohon tugas dibagi lebih merata.', 'Materi praktikum sebaiknya ditambah.'];
        $closed = $period->closes_on->lt(today());
        $groups = TeachingAssignment::query()->withoutGlobalScopes()->where('academic_term_id', $period->academic_term_id)->get();

        foreach ($groups as $assignment) {
            $memberIds = StudyGroupMember::query()->where('study_group_id', $assignment->study_group_id)->pluck('student_id');

            foreach (Student::query()->withoutGlobalScopes()->with('user')->whereIn('id', $memberIds)->get() as $student) {
                $isDemo = $student->id === $demo?->id && $student->user !== null;

                if (! ($isDemo ? $closed : random_int(1, 100) <= $chance)) {
                    continue;
                }

                $this->respond($survey, $assignment, $isDemo ? "user:{$student->user->id}" : "student:{$student->id}", $comments);
            }
        }
    }

    /**
     * @param  array<int, string>  $comments
     */
    private function respond(Survey $survey, TeachingAssignment $assignment, string $who, array $comments): void
    {
        $user = str_starts_with($who, 'user:') ? $who : "pseudo:{$who}";
        $hash = hash_hmac('sha256', "edom:{$survey->id}:assignment:{$assignment->id}:{$user}", (string) config('app.key'));

        $response = SurveyResponse::create([
            'survey_id' => $survey->id, 'user_id' => null, 'teaching_assignment_id' => $assignment->id, 'respondent_hash' => $hash, 'submitted_at' => now()->subDays(random_int(1, 20)),
        ]);

        /** @var Collection<int, SurveyQuestion> $questions */
        $questions = $survey->questions;

        foreach ($questions as $question) {
            if ($question->type === SurveyQuestionType::Text && random_int(1, 100) > 40) {
                continue;
            }

            SurveyAnswer::create([
                'survey_response_id' => $response->id, 'survey_question_id' => $question->id,
                'answer' => $question->type === SurveyQuestionType::Rating ? (string) random_int(3, 5) : $comments[array_rand($comments)],
            ]);
        }
    }
}
