<?php

namespace App\Livewire\Grades;

use App\Enums\GradeKind;
use App\Models\AcademicTerm;
use App\Models\TeachingAssignment;
use App\Services\GradingService;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Input nilai')]
class Courses extends Component
{
    #[Url(as: 'semester')]
    public string $termId = '';

    public function mount(): void
    {
        $terms = AcademicTerm::query()->orderByDesc('starts_at')->get()->keyBy('id');

        if (! $terms->has((int) $this->termId)) {
            $this->termId = (string) ($terms->first(fn (AcademicTerm $term): bool => $term->is_active)?->id ?? $terms->first()?->id ?? '');
        }
    }

    public function render(GradingService $grading)
    {
        $user = auth()->user();
        $teacher = $user->teacher;

        $rows = $teacher
            ? TeachingAssignment::query()
                ->with(['subject', 'studyGroup', 'academicTerm'])
                ->where('teacher_id', $teacher->id)
                ->where('academic_term_id', $this->termId)
                ->get()
                ->sortBy(fn (TeachingAssignment $assignment): string => $assignment->subject->name.$assignment->studyGroup->name)
                ->map(function (TeachingAssignment $assignment) use ($grading): array {
                    $roster = $grading->roster($assignment);
                    $grades = $assignment->grades()->where('kind', GradeKind::Regular)->get();
                    $complete = $grades->filter->hasComponents()->count();
                    $deadline = $grading->deadlineFor($assignment);

                    return [
                        'assignment' => $assignment,
                        'students' => $roster->count(),
                        'complete' => $complete,
                        'locked' => $assignment->areGradesLocked(),
                        'started' => $grades->contains(fn ($grade): bool => $grade->assignment_score !== null || $grade->midterm_score !== null || $grade->final_exam_score !== null),
                        'deadline' => $deadline,
                        'daysLeft' => $deadline ? (int) today()->diffInDays($deadline, false) : null,
                        'overdue' => $grading->isPastDeadline($assignment) && ! $assignment->areGradesLocked(),
                    ];
                })
                ->values()
            : collect();

        return view('livewire.grades.courses', [
            'terms' => AcademicTerm::query()->orderByDesc('starts_at')->get(),
            'rows' => $rows,
            'hasTeacher' => $teacher !== null,
        ]);
    }
}
