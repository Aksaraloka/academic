<?php

namespace App\Livewire\Curricula;

use App\Enums\SubjectType;
use App\Models\Enrollment;
use App\Services\AcademicRecord;
use App\Services\CurriculumService;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Kurikulum saya')]
class Mine extends Component
{
    public function mount(): void
    {
        abort_if(auth()->user()->student === null, 403);
    }

    public function render(CurriculumService $curricula, AcademicRecord $records)
    {
        $student = auth()->user()->student;
        $curriculum = $curricula->forStudent($student);
        $items = $curriculum ? $curriculum->items()->with('subject.prerequisites')->get() : collect();

        $best = $records->attempts($student)
            ->groupBy(fn ($grade): int => $grade->assignment->subject_id)
            ->map(fn ($attempts) => $records->best($attempts));

        $planned = Enrollment::query()->where('student_id', $student->id)->whereIn('status', ['submitted', 'approved'])
            ->with('assignments')->get()->flatMap(fn ($enrollment) => $enrollment->assignments->pluck('subject_id'))->unique();

        $rows = $items->map(fn ($item): array => [
            'item' => $item,
            'grade' => $best->get($item->subject_id),
            'passed' => (float) ($best->get($item->subject_id)?->points ?? 0) > 0,
            'planned' => $planned->contains($item->subject_id),
        ])->sortBy([['item.semester', 'asc']])->values();

        return view('livewire.curricula.mine', [
            'curriculum' => $curriculum,
            'bySemester' => $rows->groupBy(fn (array $row): int => $row['item']->semester),
            'creditsTotal' => $items->sum(fn ($item): int => (int) $item->subject->credits),
            'creditsRequired' => $items->filter(fn ($item): bool => $item->type === SubjectType::Required)->sum(fn ($item): int => (int) $item->subject->credits),
            'creditsPassed' => $rows->filter(fn (array $row): bool => $row['passed'])->sum(fn (array $row): int => (int) $row['item']->subject->credits),
            'requiredLeft' => $rows->filter(fn (array $row): bool => ! $row['passed'] && $row['item']->type === SubjectType::Required)->count(),
        ]);
    }
}
