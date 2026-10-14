<?php

namespace App\Livewire\Edom;

use App\Enums\SurveyQuestionType;
use App\Models\AcademicTerm;
use App\Models\TeachingAssignment;
use App\Services\EvaluationService;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Isi evaluasi dosen')]
class Fill extends Component
{
    #[Locked]
    public int $assignmentId;

    /** @var array<int|string, mixed> question_id => jawaban */
    public array $answers = [];

    public function mount(TeachingAssignment $assignment, EvaluationService $service): void
    {
        $student = auth()->user()->student;
        $term = AcademicTerm::current();
        $period = $term ? $service->periodFor($term) : null;

        abort_unless($student && $period && $service->assignmentsFor($student, $term)->contains('id', $assignment->id), 404);

        $this->assignmentId = $assignment->id;

        foreach ($period->survey->questions as $question) {
            $this->answers[$question->id] = $question->type === SurveyQuestionType::Multiple ? [] : '';
        }
    }

    public function submit(EvaluationService $service): mixed
    {
        $term = AcademicTerm::current();
        $period = $service->periodFor($term);

        $service->submit($period, TeachingAssignment::query()->findOrFail($this->assignmentId), auth()->user(), $this->answers);

        session()->flash('toast', 'Terima kasih! Evaluasimu sudah terkirim.');

        return $this->redirectRoute('evaluations.mine', navigate: true);
    }

    public function render(EvaluationService $service)
    {
        $term = AcademicTerm::current();
        $period = $service->periodFor($term);
        $assignment = TeachingAssignment::query()->with(['subject', 'teacher', 'studyGroup'])->findOrFail($this->assignmentId);
        $student = auth()->user()->student;

        return view('livewire.edom.fill', [
            'assignment' => $assignment,
            'period' => $period,
            'survey' => $period->survey,
            'answered' => $service->completedIds($student, $period, collect([$assignment]))->isNotEmpty(),
        ]);
    }
}
