<?php

namespace App\Livewire\Advising;

use App\Models\AcademicTerm;
use App\Models\AdvisingNote;
use App\Services\EnrollmentService;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Bimbingan akademik')]
class Mine extends Component
{
    public function mount(): void
    {
        abort_if(auth()->user()->student === null, 403);
    }

    public function render(EnrollmentService $enrollments)
    {
        $student = auth()->user()->student;
        $term = AcademicTerm::current();

        return view('livewire.advising.mine', [
            'advisor' => $term ? $enrollments->groupFor($student, $term)?->homeroomTeacher : null,
            'notes' => AdvisingNote::query()->with(['teacher', 'academicTerm'])->where('student_id', $student->id)->orderByDesc('held_on')->orderByDesc('id')->get(),
        ]);
    }
}
