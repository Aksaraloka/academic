<?php

namespace App\Livewire\Grades;

use App\Services\AcademicRecord;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Transkrip nilai')]
class Transcript extends Component
{
    #[Url(as: 'student')]
    public ?int $studentId = null;

    #[Url(as: 'child')]
    public ?int $childId = null;

    public function render(AcademicRecord $records)
    {
        $user = auth()->user();
        $student = $records->resolveStudent($user, $this->studentId, $this->childId);

        return view('livewire.grades.transcript', [
            'student' => $student?->load('program.faculty'),
            'transcript' => $student ? $records->transcript($student) : null,
            'children' => $user->guardian?->students()->get() ?? collect(),
            'pdfQuery' => array_filter(['student' => $this->studentId, 'child' => $this->childId]),
        ]);
    }
}
