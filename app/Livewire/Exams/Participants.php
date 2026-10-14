<?php

namespace App\Livewire\Exams;

use App\Enums\Role;
use App\Models\ExamSchedule;
use App\Services\ExamService;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Peserta ujian')]
class Participants extends Component
{
    #[Locked]
    public int $examId;

    public function mount(ExamSchedule $exam): void
    {
        $user = auth()->user();

        abort_unless(
            $user->hasRole(Role::Admin, Role::Principal, Role::Curriculum)
            || ($user->hasRole(Role::Teacher) && $exam->assignment->teacher_id === $user->teacher?->id),
            403,
        );

        $this->examId = $exam->id;
    }

    public function render(ExamService $service)
    {
        $exam = ExamSchedule::query()->with(['assignment.subject', 'assignment.studyGroup', 'assignment.teacher', 'assignment.academicTerm', 'room'])->findOrFail($this->examId);
        $rows = $service->participants($exam);

        return view('livewire.exams.participants', [
            'exam' => $exam,
            'rows' => $rows,
            'eligibleCount' => $rows->where('eligible', true)->count(),
            'minPercent' => (int) auth()->user()->tenant->setting('min_attendance_percent'),
        ]);
    }
}
