<?php

namespace App\Livewire\Edom;

use App\Models\AcademicTerm;
use App\Services\EvaluationService;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Evaluasi dosen')]
class Mine extends Component
{
    public function mount(): void
    {
        abort_if(auth()->user()->student === null, 403);
    }

    public function render(EvaluationService $service)
    {
        $student = auth()->user()->student;
        $term = AcademicTerm::current();
        $period = $term ? $service->periodFor($term) : null;
        $assignments = $term && $period ? $service->assignmentsFor($student, $term) : collect();
        $done = $period ? $service->completedIds($student, $period, $assignments) : collect();

        return view('livewire.edom.mine', [
            'term' => $term,
            'period' => $period,
            'assignments' => $assignments,
            'done' => $done,
        ]);
    }
}
