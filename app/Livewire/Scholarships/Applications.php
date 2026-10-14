<?php

namespace App\Livewire\Scholarships;

use App\Enums\ApplicationStatus;
use App\Models\Scholarship;
use App\Models\ScholarshipApplication;
use App\Services\ScholarshipService;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Seleksi pendaftar beasiswa')]
class Applications extends Component
{
    #[Url(as: 'status')]
    public string $status = 'submitted';

    #[Url(as: 'scholarship')]
    public string $scholarshipId = '';

    #[Url(as: 'search')]
    public string $search = '';

    public ?int $reviewingId = null;

    public string $note = '';

    public function mount(ScholarshipService $service): void
    {
        abort_unless($service->isManager(auth()->user()), 403);
    }

    public function review(int $id): void
    {
        $this->reviewingId = ScholarshipApplication::query()->findOrFail($id)->id;
        $this->note = '';
        $this->resetErrorBag();
    }

    public function accept(ScholarshipService $service): void
    {
        $this->validate(['note' => ['nullable', 'string', 'max:500']], [], ['note' => 'catatan']);

        $service->accept(ScholarshipApplication::query()->findOrFail($this->reviewingId), auth()->user(), $this->note);

        $this->reset(['reviewingId', 'note']);
        $this->dispatch('toast', message: 'Pendaftar diterima sebagai penerima beasiswa.', type: 'success');
    }

    public function reject(ScholarshipService $service): void
    {
        $this->validate(['note' => ['required', 'string', 'min:5', 'max:500']], [], ['note' => 'alasan']);

        $service->reject(ScholarshipApplication::query()->findOrFail($this->reviewingId), auth()->user(), $this->note);

        $this->reset(['reviewingId', 'note']);
        $this->dispatch('toast', message: 'Pendaftar tidak diterima.', type: 'success');
    }

    public function render()
    {
        $keyword = trim($this->search);
        $scope = ScholarshipApplication::query()->when($this->scholarshipId !== '', fn ($query) => $query->where('scholarship_id', (int) $this->scholarshipId));
        $counts = (clone $scope)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $applications = (clone $scope)->with(['student.program', 'scholarship'])
            ->when(ApplicationStatus::tryFrom($this->status), fn ($query, $status) => $query->where('status', $status))
            ->when($keyword !== '', fn ($query) => $query->whereHas('student', fn ($student) => $student->where('name', 'like', "%{$keyword}%")->orWhere('nim', 'like', "%{$keyword}%")))
            ->orderByDesc('gpa')->orderBy('id')->limit(150)->get();

        return view('livewire.scholarships.applications', [
            'applications' => $applications,
            'counts' => $counts,
            'statuses' => ApplicationStatus::cases(),
            'scholarships' => Scholarship::query()->orderByDesc('closes_on')->get(['id', 'name']),
            'reviewing' => $this->reviewingId ? $applications->firstWhere('id', $this->reviewingId) : null,
        ]);
    }
}
