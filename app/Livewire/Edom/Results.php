<?php

namespace App\Livewire\Edom;

use App\Enums\Role;
use App\Models\AcademicTerm;
use App\Models\TeachingAssignment;
use App\Services\EvaluationService;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Hasil evaluasi dosen')]
class Results extends Component
{
    #[Url(as: 'term')]
    public string $termId = '';

    private function term(): ?AcademicTerm
    {
        return ($this->termId !== '' ? AcademicTerm::query()->find($this->termId) : null)
            ?? AcademicTerm::current()
            ?? AcademicTerm::query()->orderByDesc('starts_at')->first();
    }

    public function render(EvaluationService $service)
    {
        $term = $this->term();
        $user = auth()->user();
        $period = $term ? $service->periodFor($term) : null;

        $rows = collect();

        if ($period) {
            $rows = TeachingAssignment::query()->with(['subject', 'teacher', 'studyGroup'])
                ->where('academic_term_id', $term->id)
                ->when($user->hasRole(Role::Teacher), fn ($query) => $query->where('teacher_id', $user->teacher?->id ?? 0))
                ->get()
                ->sortBy(fn (TeachingAssignment $assignment): string => $assignment->teacher->name.$assignment->subject->name)
                ->map(fn (TeachingAssignment $assignment): array => ['assignment' => $assignment, 'summary' => $service->summary($period, $assignment)])
                ->filter(fn (array $row): bool => $row['summary']['responses'] > 0)
                ->values();
        }

        return view('livewire.edom.results', [
            'term' => $term,
            'terms' => AcademicTerm::query()->orderByDesc('starts_at')->get(),
            'period' => $period,
            'rows' => $rows,
            'minResponses' => EvaluationService::MIN_RESPONSES,
            'isTeacher' => $user->hasRole(Role::Teacher),
        ]);
    }
}
