<?php

namespace App\Livewire\Status;

use App\Enums\StatusRequestStatus;
use App\Enums\StudentStatus;
use App\Models\AcademicTerm;
use App\Models\StatusRequest;
use App\Models\Student;
use App\Services\StudentStatusService;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Status mahasiswa')]
class Manage extends Component
{
    #[Url(as: 'search')]
    public string $search = '';

    public ?int $studentId = null;

    public string $termId = '';

    public string $status = 'aktif';

    public string $reason = '';

    public ?int $rejectingId = null;

    public string $rejectNote = '';

    public function approve(int $requestId, StudentStatusService $service): void
    {
        $service->decide(StatusRequest::query()->findOrFail($requestId), auth()->user(), true);

        $this->dispatch('toast', message: 'Pengajuan disetujui dan status diperbarui.', type: 'success');
    }

    public function startReject(int $requestId): void
    {
        $this->rejectingId = $requestId;
        $this->rejectNote = '';
        $this->resetErrorBag();
    }

    public function cancelReject(): void
    {
        $this->reset(['rejectingId', 'rejectNote']);
    }

    public function reject(StudentStatusService $service): void
    {
        $this->validate(['rejectNote' => ['required', 'string', 'min:5', 'max:500']], [], ['rejectNote' => 'alasan penolakan']);

        $service->decide(StatusRequest::query()->findOrFail($this->rejectingId), auth()->user(), false, $this->rejectNote);

        $this->cancelReject();
        $this->dispatch('toast', message: 'Pengajuan ditolak.', type: 'success');
    }

    public function pick(int $studentId): void
    {
        $student = Student::query()->findOrFail($studentId);

        $this->studentId = $student->id;
        $this->status = $student->status->value;
        $this->termId = (string) (AcademicTerm::current()?->id ?? '');
        $this->reset('reason');
        $this->resetErrorBag();
    }

    public function saveStatus(StudentStatusService $service): void
    {
        abort_unless($service->canDecide(auth()->user()), 403);

        $this->validate([
            'studentId' => ['required', 'exists:students,id'],
            'termId' => ['required', 'exists:academic_terms,id'],
            'status' => ['required', 'in:'.implode(',', array_map(fn (StudentStatus $status): string => $status->value, StudentStatus::cases()))],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [], ['studentId' => 'mahasiswa', 'termId' => 'semester', 'status' => 'status', 'reason' => 'alasan']);

        $service->setStatus(Student::query()->findOrFail($this->studentId), AcademicTerm::query()->findOrFail($this->termId), StudentStatus::from($this->status), trim($this->reason), auth()->user());

        $this->reset('reason');
        $this->dispatch('toast', message: 'Status mahasiswa dicatat.', type: 'success');
    }

    public function render(StudentStatusService $service)
    {
        $student = $this->studentId ? Student::query()->find($this->studentId) : null;
        $term = trim($this->search);

        return view('livewire.status.manage', [
            'pending' => StatusRequest::query()->with(['student', 'academicTerm'])->where('status', StatusRequestStatus::Pending)->oldest()->get(),
            'matches' => strlen($term) >= 2
                ? Student::query()->where(fn ($query) => $query->where('name', 'like', "%{$term}%")->orWhere('nim', 'like', "%{$term}%"))->orderBy('name')->limit(8)->get()
                : collect(),
            'student' => $student,
            'history' => $student ? $service->history($student) : collect(),
            'terms' => AcademicTerm::query()->orderByDesc('starts_at')->get(),
            'statuses' => StudentStatus::cases(),
            'recent' => StatusRequest::query()->with(['student', 'academicTerm'])->where('status', '!=', StatusRequestStatus::Pending)->latest('decided_at')->limit(8)->get(),
        ]);
    }
}
