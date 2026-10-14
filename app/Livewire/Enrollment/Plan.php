<?php

namespace App\Livewire\Enrollment;

use App\Enums\StudentStatus;
use App\Models\AcademicTerm;
use App\Models\Enrollment;
use App\Services\EnrollmentService;
use App\Services\FinanceService;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('KRS')]
class Plan extends Component
{
    /** @var array<int, string> id penugasan kuliah yang dicentang */
    public array $selected = [];

    public function mount(EnrollmentService $service): void
    {
        $student = auth()->user()->student;
        $term = AcademicTerm::current();

        abort_if($student === null, 403);

        if ($term === null) {
            return;
        }

        $enrollment = $service->isWindowOpen($term) && $student->status === StudentStatus::Active
            ? $service->start($student, $term)
            : Enrollment::query()->where('academic_term_id', $term->id)->where('student_id', $student->id)->first();

        $this->selected = $enrollment?->assignments()->pluck('teaching_assignments.id')->map(fn (int $id): string => (string) $id)->all() ?? [];
    }

    public function save(EnrollmentService $service): void
    {
        $service->saveSelection($this->enrollment($service), $this->selected);

        $this->dispatch('toast', message: 'Draf KRS disimpan.', type: 'success');
    }

    public function submit(EnrollmentService $service): void
    {
        if ($this->selected === []) {
            throw ValidationException::withMessages(['selection' => 'Pilih minimal satu mata kuliah sebelum mengajukan KRS.']);
        }

        $enrollment = $this->enrollment($service);

        $service->saveSelection($enrollment, $this->selected);
        $service->submit($enrollment->refresh());

        $this->dispatch('toast', message: 'KRS diajukan ke dosen wali.', type: 'success');
    }

    public function withdraw(EnrollmentService $service): void
    {
        $service->withdraw($this->enrollment($service));

        $this->dispatch('toast', message: 'Pengajuan ditarik. KRS bisa diubah lagi.', type: 'success');
    }

    private function enrollment(EnrollmentService $service): Enrollment
    {
        $term = AcademicTerm::current();
        abort_if($term === null, 404);

        return $service->start(auth()->user()->student, $term);
    }

    public function render(EnrollmentService $service, FinanceService $finance)
    {
        $student = auth()->user()->student;
        $term = AcademicTerm::current();

        if ($term === null) {
            return view('livewire.enrollment.plan', ['term' => null]);
        }

        $enrollment = Enrollment::query()->with(['assignments.subject', 'assignments.teacher', 'assignments.studyGroup', 'assignments.schedules.timeSlot', 'decider'])
            ->where('academic_term_id', $term->id)->where('student_id', $student->id)->first();

        $offerings = $service->offerings($student, $term);
        $ownGroupId = $service->groupFor($student, $term)?->id;
        $chosen = $offerings->whereIn('id', array_map('intval', $this->selected));
        $clearance = $finance->krsClearance($student, $term);
        $editable = $enrollment !== null && $enrollment->status->isEditable() && $service->isWindowOpen($term) && $student->status === StudentStatus::Active && $clearance['allowed'];

        return view('livewire.enrollment.plan', [
            'term' => $term,
            'student' => $student,
            'enrollment' => $enrollment,
            'editable' => $editable,
            'unpaid' => $clearance['allowed'] ? null : $clearance['outstanding'],
            'inactiveStatus' => $student->status === StudentStatus::Active ? null : $student->status,
            'windowOpen' => $service->isWindowOpen($term),
            'window' => $service->window($term),
            'offerings' => $offerings,
            'ownGroupId' => $ownGroupId,
            'maxCredits' => $service->maxCredits($student, $term),
            'chosenCredits' => (int) $chosen->sum(fn ($assignment): int => (int) $assignment->subject->credits),
            'conflicts' => $service->conflicts($chosen),
            'submittedList' => $enrollment && ! $editable ? $enrollment->assignments : collect(),
        ]);
    }
}
