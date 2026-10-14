<?php

namespace App\Livewire\Exams;

use App\Enums\ExamType;
use App\Enums\Role;
use App\Models\AcademicTerm;
use App\Models\ExamSchedule;
use App\Models\Student;
use App\Services\ExamService;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Kartu ujian')]
class Card extends Component
{
    #[Url(as: 'type')]
    public string $type = '';

    /** Orang tua memilih anak lewat ?child=ID. */
    #[Url(as: 'child')]
    public ?int $childId = null;

    private function student(): ?Student
    {
        $user = auth()->user();

        if ($user->hasRole(Role::Student)) {
            return $user->student;
        }

        $children = $user->guardian?->students()->get() ?? collect();

        return $children->firstWhere('id', $this->childId) ?? $children->first();
    }

    public function render(ExamService $service)
    {
        $user = auth()->user();
        $student = $this->student();
        $term = AcademicTerm::current();

        if (ExamType::tryFrom($this->type) === null) {
            $hasMidterm = $term && ExamSchedule::query()->where('academic_term_id', $term->id)->where('type', ExamType::Midterm)->exists();
            $this->type = $term && ! $hasMidterm ? ExamType::Final->value : ExamType::Midterm->value;
        }

        $type = ExamType::from($this->type);
        $rows = $student && $term ? $service->cardRows($student, $term, $type) : collect();
        $blocker = $student && $term ? $service->cardBlocker($student, $term) : null;
        $issued = $blocker === null && $rows->contains(fn (array $row): bool => $row['exam'] !== null && $row['eligible']);

        return view('livewire.exams.card', [
            'student' => $student,
            'children' => $user->hasRole(Role::Guardian) ? ($user->guardian?->students()->get() ?? collect()) : collect(),
            'term' => $term,
            'examType' => $type,
            'types' => ExamType::cases(),
            'rows' => $rows,
            'blocker' => $blocker,
            'issued' => $issued,
            'minPercent' => (int) $user->tenant->setting('min_attendance_percent'),
            'pdfQuery' => array_filter(['type' => $type->value, 'child' => $this->childId]),
        ]);
    }
}
