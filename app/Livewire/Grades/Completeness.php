<?php

namespace App\Livewire\Grades;

use App\Enums\GradeKind;
use App\Models\AcademicTerm;
use App\Models\TeachingAssignment;
use App\Services\AuditLogger;
use App\Services\GradingService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Kelengkapan nilai')]
#[Layout('layouts.app', ['fullWidth' => true])]
class Completeness extends Component
{
    #[Url(as: 'semester')]
    public string $termId = '';

    public ?int $extendingId = null;

    public string $extendUntil = '';

    public function mount(): void
    {
        $terms = AcademicTerm::query()->orderByDesc('starts_at')->get()->keyBy('id');

        if (! $terms->has((int) $this->termId)) {
            $this->termId = (string) ($terms->first(fn (AcademicTerm $term): bool => $term->is_active)?->id ?? $terms->first()?->id ?? '');
        }
    }

    public function startExtend(int $assignmentId): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $this->extendingId = $assignmentId;
        $this->extendUntil = today()->addDays(7)->toDateString();
    }

    public function cancelExtend(): void
    {
        $this->reset(['extendingId', 'extendUntil']);
    }

    public function extend(AuditLogger $audit): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $data = $this->validate(['extendUntil' => ['required', 'date', 'after_or_equal:today']], [], ['extendUntil' => 'tanggal perpanjangan']);
        $assignment = TeachingAssignment::query()->findOrFail($this->extendingId);

        $assignment->update(['grading_extended_until' => $data['extendUntil']]);
        $audit->log('grades.deadline_extended', $assignment, [], ['until' => $data['extendUntil']]);

        $this->cancelExtend();
        $this->dispatch('toast', message: 'Batas input nilai diperpanjang.', type: 'success');
    }

    public function render(GradingService $grading)
    {
        $rows = TeachingAssignment::query()
            ->with(['subject', 'studyGroup', 'teacher', 'academicTerm'])
            ->where('academic_term_id', $this->termId)
            ->get()
            ->sortBy(fn (TeachingAssignment $assignment): string => $assignment->subject->name.$assignment->studyGroup->name)
            ->map(function (TeachingAssignment $assignment) use ($grading): array {
                $students = $grading->roster($assignment)->count();
                $grades = $assignment->grades()->where('kind', GradeKind::Regular)->get();
                $complete = $grades->filter->hasComponents()->count();
                $locked = $assignment->areGradesLocked();
                $started = $grades->contains(fn ($grade): bool => $grade->assignment_score !== null || $grade->midterm_score !== null || $grade->final_exam_score !== null);

                return [
                    'assignment' => $assignment,
                    'students' => $students,
                    'complete' => $complete,
                    'locked' => $locked,
                    'status' => $locked ? 'locked' : ($started ? 'draft' : 'empty'),
                    'deadline' => $grading->deadlineFor($assignment),
                    'overdue' => ! $locked && $grading->isPastDeadline($assignment),
                    'extended' => $assignment->grading_extended_until !== null,
                ];
            })
            ->values();

        return view('livewire.grades.completeness', [
            'terms' => AcademicTerm::query()->orderByDesc('starts_at')->get(),
            'rows' => $rows,
            'counts' => [
                'total' => $rows->count(),
                'locked' => $rows->where('status', 'locked')->count(),
                'draft' => $rows->where('status', 'draft')->count(),
                'empty' => $rows->where('status', 'empty')->count(),
                'overdue' => $rows->where('overdue', true)->count(),
            ],
            'isAdmin' => auth()->user()->isAdmin(),
        ]);
    }
}
