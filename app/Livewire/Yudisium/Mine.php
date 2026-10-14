<?php

namespace App\Livewire\Yudisium;

use App\Models\Thesis;
use App\Services\YudisiumService;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Status kelulusan')]
class Mine extends Component
{
    public function mount(): void
    {
        abort_if(auth()->user()->student === null, 403);
    }

    public function render(YudisiumService $service)
    {
        $student = auth()->user()->student;

        return view('livewire.yudisium.mine', [
            'student' => $student,
            'result' => $service->evaluate($student),
            'decision' => $service->decisionFor($student),
            'thesis' => Thesis::query()->where('student_id', $student->id)->where('status', '!=', 'rejected')->latest('id')->first(),
        ]);
    }
}
