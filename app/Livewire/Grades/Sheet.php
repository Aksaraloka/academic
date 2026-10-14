<?php

namespace App\Livewire\Grades;

use App\Enums\GradeKind;
use App\Enums\GradeRevisionStatus;
use App\Enums\Role;
use App\Models\Grade;
use App\Models\Student;
use App\Models\TeachingAssignment;
use App\Services\GradingService;
use App\Support\GradeScale;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Lembar nilai')]
#[Layout('layouts.app', ['fullWidth' => true])]
class Sheet extends Component
{
    public TeachingAssignment $assignment;

    /** @var array<int|string, array<string, string>> student_id => [assignment, midterm, final] */
    public array $scores = [];

    /** @var array<string, int|string> */
    public array $weights = [];

    /** @var array<int|string, string> student_id => nilai remedial */
    public array $remedial = [];

    public ?int $revisingGradeId = null;

    /** @var array<string, string> */
    public array $revision = ['assignment' => '', 'midterm' => '', 'final' => ''];

    public string $revisionReason = '';

    public string $nim = '';

    public function mount(TeachingAssignment $assignment, GradingService $grading): void
    {
        $user = auth()->user();

        abort_unless(
            $user->teacher?->id === $assignment->teacher_id || $user->hasRole(Role::Admin, Role::Principal, Role::Curriculum),
            403,
        );

        $this->assignment = $assignment->load(['subject', 'studyGroup', 'academicTerm', 'teacher']);
        $this->loadState($grading);
    }

    private function loadState(GradingService $grading): void
    {
        $this->weights = array_map('strval', $grading->scale(auth()->user())->weightsFor($this->assignment));
        $grades = $this->assignment->grades()->get()->groupBy('student_id');

        foreach ($grading->roster($this->assignment) as $student) {
            $regular = $grades->get($student->id)?->firstWhere('kind', GradeKind::Regular);
            $remedial = $grades->get($student->id)?->firstWhere('kind', GradeKind::Remedial);

            $this->scores[$student->id] = [
                'assignment' => $this->display($regular?->assignment_score),
                'midterm' => $this->display($regular?->midterm_score),
                'final' => $this->display($regular?->final_exam_score),
            ];
            $this->remedial[$student->id] = $this->display($remedial?->total_score);
        }
    }

    private function display(mixed $value): string
    {
        return $value === null ? '' : rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }

    private function number(mixed $value): ?float
    {
        return is_numeric($value) && $value >= 0 && $value <= 100 ? (float) $value : null;
    }

    public function saveWeights(GradingService $grading): void
    {
        $grading->saveWeights($this->assignment, auth()->user(), $this->weights);

        $this->assignment->refresh();
        $this->dispatch('toast', message: 'Bobot penilaian disimpan.', type: 'success');
    }

    public function save(GradingService $grading): void
    {
        $count = $grading->saveScores($this->assignment, auth()->user(), $this->scores);

        $this->dispatch('toast', message: "Draf nilai {$count} mahasiswa disimpan.", type: 'success');
    }

    public function lock(GradingService $grading): void
    {
        $grading->saveScores($this->assignment, auth()->user(), $this->scores);
        $grading->lock($this->assignment, auth()->user());

        $this->assignment->refresh();
        $this->dispatch('toast', message: 'Nilai dikunci dan sudah tampil di KHS mahasiswa.', type: 'success');
    }

    public function addRepeater(GradingService $grading): void
    {
        $student = $grading->addRepeater($this->assignment, auth()->user(), $this->nim);

        $this->scores[$student->id] = ['assignment' => '', 'midterm' => '', 'final' => ''];
        $this->remedial[$student->id] = '';
        $this->nim = '';
        $this->dispatch('toast', message: "{$student->name} ditambahkan sebagai mahasiswa mengulang.", type: 'success');
    }

    public function saveRemedial(int $studentId, GradingService $grading): void
    {
        $grade = $grading->saveRemedial($this->assignment, auth()->user(), $studentId, $this->remedial[$studentId] ?? null);

        $this->dispatch('toast', message: "Nilai remedial disimpan (huruf {$grade->letter}). Hasil terbaik dipakai di transkrip.", type: 'success');
    }

    public function startRevision(int $gradeId): void
    {
        $grade = Grade::query()->where('teaching_assignment_id', $this->assignment->id)->findOrFail($gradeId);

        $this->revisingGradeId = $grade->id;
        $this->revision = [
            'assignment' => $this->display($grade->assignment_score),
            'midterm' => $this->display($grade->midterm_score),
            'final' => $this->display($grade->final_exam_score),
        ];
        $this->revisionReason = '';
    }

    public function cancelRevision(): void
    {
        $this->reset(['revisingGradeId', 'revisionReason']);
        $this->revision = ['assignment' => '', 'midterm' => '', 'final' => ''];
    }

    public function submitRevision(GradingService $grading): void
    {
        $grade = Grade::query()->where('teaching_assignment_id', $this->assignment->id)->findOrFail($this->revisingGradeId);

        $grading->requestRevision($grade, auth()->user(), $this->revision, $this->revisionReason);

        $this->cancelRevision();
        $this->dispatch('toast', message: 'Pengajuan revisi dikirim ke Kaprodi.', type: 'success');
    }

    public function render(GradingService $grading)
    {
        $user = auth()->user();
        $scale = $grading->scale($user);
        $weights = $scale->weightsFor($this->assignment);
        $locked = $this->assignment->areGradesLocked();
        $roster = $grading->roster($this->assignment);
        $grades = $this->assignment->grades()->with(['revisions' => fn ($query) => $query->where('status', GradeRevisionStatus::Pending)])->get()->groupBy('student_id');
        $attendance = $locked ? [] : $grading->attendanceScores($this->assignment, $roster->pluck('id'));
        $blockReason = $grading->inputBlockReason($this->assignment, $user);
        $isOwner = $user->teacher?->id === $this->assignment->teacher_id;
        $termLocked = (bool) $this->assignment->academicTerm?->is_locked;

        $rows = $roster->map(function (Student $student) use ($grades, $locked, $attendance, $weights, $scale, $isOwner, $termLocked): array {
            $regular = $grades->get($student->id)?->firstWhere('kind', GradeKind::Regular);
            $remedial = $grades->get($student->id)?->firstWhere('kind', GradeKind::Remedial);

            if ($locked && $regular) {
                $values = $regular->scores();
            } else {
                $input = $this->scores[$student->id] ?? [];
                $values = [
                    'assignment' => $this->number($input['assignment'] ?? null),
                    'midterm' => $this->number($input['midterm'] ?? null),
                    'final' => $this->number($input['final'] ?? null),
                    'attendance' => $attendance[$student->id] ?? 0.0,
                ];
                $values['total'] = GradeScale::total($values, $weights);
                $values['letter'] = $scale->letterFor($values['total']);
            }

            return [
                'student' => $student,
                'regular' => $regular,
                'remedial' => $remedial,
                'values' => $values,
                'canRemedial' => $locked && $isOwner && ! $termLocked && $values['letter'] !== null && in_array($values['letter'], $scale->remedialLetters, true),
                'pending' => $regular?->revisions->first(),
            ];
        });

        $totals = $rows->pluck('values.total')->filter(fn ($total) => $total !== null);

        return view('livewire.grades.sheet', [
            'rows' => $rows,
            'locked' => $locked,
            'canEdit' => $blockReason === null,
            'blockReason' => $blockReason,
            'isOwner' => $isOwner,
            'deadline' => $grading->deadlineFor($this->assignment),
            'components' => GradeScale::COMPONENTS,
            'weightSum' => array_sum(array_map('intval', $this->weights)),
            'savedWeights' => $weights,
            'stats' => [
                'students' => $rows->count(),
                'complete' => $rows->filter(fn (array $row): bool => $row['values']['total'] !== null)->count(),
                'average' => $totals->isEmpty() ? null : round($totals->avg(), 2),
                'letters' => collect(['A', 'B', 'C', 'D', 'E'])->mapWithKeys(fn (string $letter): array => [$letter => $rows->where('values.letter', $letter)->count()]),
            ],
            'revisingGrade' => $this->revisingGradeId ? $grades->flatten()->firstWhere('id', $this->revisingGradeId) : null,
            'remedialLetters' => $scale->remedialLetters,
        ]);
    }
}
