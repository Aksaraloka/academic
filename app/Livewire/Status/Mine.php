<?php

namespace App\Livewire\Status;

use App\Enums\StatusRequestType;
use App\Models\AcademicTerm;
use App\Models\StatusRequest;
use App\Services\StudentStatusService;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Status akademik')]
class Mine extends Component
{
    public string $type = 'leave';

    public string $reason = '';

    public function mount(): void
    {
        abort_if(auth()->user()->student === null, 403);

        $this->type = auth()->user()->student->status->canReactivate() ? 'reactivation' : 'leave';
    }

    public function submit(StudentStatusService $service): void
    {
        $this->validate([
            'type' => ['required', 'in:leave,reactivation'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ], [], ['type' => 'jenis pengajuan', 'reason' => 'alasan']);

        $service->request(auth()->user()->student, StatusRequestType::from($this->type), $this->reason);

        $this->reset('reason');
        $this->dispatch('toast', message: 'Pengajuan dikirim. Tunggu keputusan BAAK.', type: 'success');
    }

    public function render(StudentStatusService $service)
    {
        $student = auth()->user()->student;
        $term = AcademicTerm::current();

        return view('livewire.status.mine', [
            'student' => $student->fresh(),
            'term' => $term,
            'history' => $service->history($student),
            'requests' => StatusRequest::query()->with(['academicTerm', 'decider'])->where('student_id', $student->id)->latest()->get(),
            'leaveOpen' => $term ? $service->isLeaveWindowOpen($term) : false,
        ]);
    }
}
