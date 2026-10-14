<?php

namespace App\Livewire\Edom;

use App\Enums\Role;
use App\Enums\SurveyCategory;
use App\Models\AcademicTerm;
use App\Models\EvaluationPeriod;
use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Services\AuditLogger;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Periode evaluasi dosen')]
class Periods extends Component
{
    public ?int $editingId = null;

    public string $termId = '';

    public string $surveyId = '';

    public string $opens_on = '';

    public string $closes_on = '';

    public bool $gates_grades = true;

    public bool $is_published = false;

    private function canManage(): bool
    {
        return auth()->user()->hasRole(Role::Admin, Role::Curriculum);
    }

    public function mount(): void
    {
        $this->opens_on = today()->toDateString();
        $this->closes_on = today()->addDays(21)->toDateString();
        $this->termId = (string) (AcademicTerm::current()?->id ?? '');
    }

    public function edit(int $periodId): void
    {
        abort_unless($this->canManage(), 403);

        $period = EvaluationPeriod::query()->findOrFail($periodId);

        $this->editingId = $period->id;
        $this->termId = (string) $period->academic_term_id;
        $this->surveyId = (string) $period->survey_id;
        $this->opens_on = $period->opens_on->toDateString();
        $this->closes_on = $period->closes_on->toDateString();
        $this->gates_grades = $period->gates_grades;
        $this->is_published = $period->is_published;
        $this->resetErrorBag();
    }

    public function cancel(): void
    {
        $this->reset(['editingId', 'surveyId']);
        $this->opens_on = today()->toDateString();
        $this->closes_on = today()->addDays(21)->toDateString();
        $this->gates_grades = true;
        $this->is_published = false;
        $this->resetErrorBag();
    }

    public function save(AuditLogger $audit): void
    {
        abort_unless($this->canManage(), 403);

        $data = $this->validate([
            'termId' => ['required', 'exists:academic_terms,id'],
            'surveyId' => ['required', 'exists:surveys,id'],
            'opens_on' => ['required', 'date'],
            'closes_on' => ['required', 'date', 'after_or_equal:opens_on'],
            'gates_grades' => ['boolean'],
            'is_published' => ['boolean'],
        ], [], ['termId' => 'semester', 'surveyId' => 'kuesioner', 'opens_on' => 'tanggal buka', 'closes_on' => 'tanggal tutup']);

        $survey = Survey::query()->with('questions')->findOrFail($data['surveyId']);

        if ($survey->category !== SurveyCategory::LecturerEvaluation) {
            throw ValidationException::withMessages(['surveyId' => 'Pilih kuesioner berkategori Evaluasi dosen.']);
        }

        if ($data['is_published'] && $survey->questions->isEmpty()) {
            throw ValidationException::withMessages(['surveyId' => 'Kuesioner ini belum punya pertanyaan.']);
        }

        $duplicate = EvaluationPeriod::query()->where('academic_term_id', $data['termId'])->when($this->editingId, fn ($query) => $query->whereKeyNot($this->editingId))->exists();

        if ($duplicate) {
            throw ValidationException::withMessages(['termId' => 'Semester ini sudah punya periode evaluasi.']);
        }

        $attributes = [
            'academic_term_id' => $data['termId'], 'survey_id' => $data['surveyId'], 'opens_on' => $data['opens_on'],
            'closes_on' => $data['closes_on'], 'gates_grades' => $data['gates_grades'], 'is_published' => $data['is_published'],
        ];

        if ($this->editingId) {
            $period = EvaluationPeriod::query()->findOrFail($this->editingId);
            $period->update($attributes);
            $audit->log('edom.updated', $period, [], $attributes);
        } else {
            $period = EvaluationPeriod::create($attributes + ['tenant_id' => auth()->user()->tenant_id]);
            $audit->log('edom.created', $period, [], $attributes);
        }

        $this->cancel();
        $this->dispatch('toast', message: 'Periode evaluasi disimpan.', type: 'success');
    }

    public function render()
    {
        $periods = EvaluationPeriod::query()->with(['academicTerm', 'survey'])->latest('opens_on')->get();

        return view('livewire.edom.periods', [
            'periods' => $periods,
            'responses' => SurveyResponse::query()->whereIn('survey_id', $periods->pluck('survey_id'))->whereNotNull('teaching_assignment_id')
                ->selectRaw('survey_id, count(*) as total')->groupBy('survey_id')->pluck('total', 'survey_id'),
            'terms' => AcademicTerm::query()->orderByDesc('starts_at')->get(),
            'surveys' => Survey::query()->where('category', SurveyCategory::LecturerEvaluation)->orderByDesc('id')->get(),
            'canManage' => $this->canManage(),
        ]);
    }
}
