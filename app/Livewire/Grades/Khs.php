<?php

namespace App\Livewire\Grades;

use App\Enums\Role;
use App\Services\AcademicRecord;
use App\Services\EvaluationService;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('KHS')]
class Khs extends Component
{
    #[Url(as: 'semester')]
    public string $termId = '';

    /** Staf melihat mahasiswa tertentu lewat ?student=ID; orang tua memilih anak lewat ?child=ID. */
    #[Url(as: 'student')]
    public ?int $studentId = null;

    #[Url(as: 'child')]
    public ?int $childId = null;

    public function render(AcademicRecord $records, EvaluationService $evaluations)
    {
        $user = auth()->user();
        $student = $records->resolveStudent($user, $this->studentId, $this->childId);
        $terms = $student ? $records->termsWithGrades($student) : collect();

        $term = $student ? $records->defaultTerm($student, (int) $this->termId ?: null) : null;

        $gate = $student && $term && $user->hasRole(Role::Student, Role::Guardian)
            ? $evaluations->gradeGate($student, $term)
            : ['blocked' => false, 'pending' => collect(), 'period' => null];

        return view('livewire.grades.khs', [
            'gate' => $gate,
            'student' => $student?->load('program.faculty'),
            'terms' => $terms,
            'term' => $term,
            'khs' => $student && $term && ! $gate['blocked'] ? $records->khs($student, $term) : null,
            'children' => $user->guardian?->students()->get() ?? collect(),
            'pdfQuery' => array_filter(['semester' => $term?->id, 'student' => $this->studentId, 'child' => $this->childId]),
        ]);
    }
}
