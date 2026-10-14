<?php

namespace App\Livewire\Scholarships;

use App\Enums\ApplicationStatus;
use App\Models\Scholarship;
use App\Models\ScholarshipApplication;
use App\Services\ScholarshipService;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Beasiswa')]
class Mine extends Component
{
    public ?int $applyingId = null;

    public string $motivation = '';

    public function mount(): void
    {
        abort_if(auth()->user()->student === null, 403);
    }

    public function startApply(int $id): void
    {
        $this->applyingId = Scholarship::query()->where('is_active', true)->findOrFail($id)->id;
        $this->motivation = '';
        $this->resetErrorBag();
    }

    public function apply(ScholarshipService $service): void
    {
        $this->validate(['motivation' => ['required', 'string', 'min:20', 'max:2000']], [], ['motivation' => 'motivasi']);

        $service->apply(auth()->user()->student, Scholarship::query()->findOrFail($this->applyingId), $this->motivation);

        $this->reset(['applyingId', 'motivation']);
        $this->dispatch('toast', message: 'Pendaftaran dikirim. Hasil seleksi muncul di sini.', type: 'success');
    }

    public function withdraw(int $id, ScholarshipService $service): void
    {
        $service->withdraw(ScholarshipApplication::query()->where('student_id', auth()->user()->student->id)->findOrFail($id), auth()->user());

        $this->dispatch('toast', message: 'Pendaftaran dibatalkan.', type: 'success');
    }

    public function render(ScholarshipService $service)
    {
        $student = auth()->user()->student;
        $applications = ScholarshipApplication::query()->with('scholarship')->where('student_id', $student->id)->latest('id')->get();
        $mine = $applications->keyBy('scholarship_id');

        $scholarships = Scholarship::query()->where('is_active', true)->whereDate('closes_on', '>=', today()->subMonths(1))
            ->orderBy('closes_on')->get()
            ->each(function (Scholarship $scholarship) use ($service, $student, $mine): void {
                $application = $mine->get($scholarship->id);
                $scholarship->setAttribute('blockers', $application && $application->status !== ApplicationStatus::Withdrawn ? [] : $service->blockers($student, $scholarship));
            });

        return view('livewire.scholarships.mine', [
            'scholarships' => $scholarships,
            'applications' => $applications,
            'mine' => $mine,
        ]);
    }
}
