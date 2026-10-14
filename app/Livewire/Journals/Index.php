<?php

namespace App\Livewire\Journals;

use App\Enums\Role;
use App\Models\AcademicTerm;
use App\Models\AttendanceSession;
use App\Models\ClassJournal;
use App\Models\TeachingAssignment;
use App\Services\EvaluationService;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Jurnal perkuliahan')]
class Index extends Component
{
    #[Url(as: 'term')]
    public string $termId = '';

    private function term(): ?AcademicTerm
    {
        return ($this->termId !== '' ? AcademicTerm::query()->find($this->termId) : null)
            ?? AcademicTerm::current()
            ?? AcademicTerm::query()->orderByDesc('starts_at')->first();
    }

    public function render(EvaluationService $evaluations)
    {
        $user = auth()->user();
        $term = $this->term();

        if ($term === null) {
            return view('livewire.journals.index', ['term' => null]);
        }

        $query = TeachingAssignment::query()->with(['subject', 'teacher', 'studyGroup'])->where('academic_term_id', $term->id);

        $assignments = match (true) {
            $user->hasRole(Role::Student) => $evaluations->assignmentsFor($user->student, $term),
            $user->hasRole(Role::Teacher) => $query->where('teacher_id', $user->teacher?->id ?? 0)->get(),
            default => $query->get(),
        };

        $ids = $assignments->pluck('id');
        $held = AttendanceSession::query()->join('schedules', 'schedules.id', '=', 'attendance_sessions.schedule_id')
            ->whereIn('schedules.teaching_assignment_id', $ids)->selectRaw('schedules.teaching_assignment_id as aid, count(*) as total')->groupBy('aid')->pluck('total', 'aid');
        $filled = ClassJournal::query()->whereIn('teaching_assignment_id', $ids)->selectRaw('teaching_assignment_id as aid, count(*) as total')->groupBy('aid')->pluck('total', 'aid');

        return view('livewire.journals.index', [
            'term' => $term,
            'terms' => AcademicTerm::query()->orderByDesc('starts_at')->get(),
            'rows' => $assignments->sortBy(fn (TeachingAssignment $assignment): string => $assignment->subject->name.$assignment->studyGroup->name)->values()
                ->map(fn (TeachingAssignment $assignment): array => ['assignment' => $assignment, 'held' => (int) ($held[$assignment->id] ?? 0), 'filled' => (int) ($filled[$assignment->id] ?? 0)]),
            'isStudent' => $user->hasRole(Role::Student),
        ]);
    }
}
