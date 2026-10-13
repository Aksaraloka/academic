<?php

namespace App\Livewire\Wisuda;

use App\Enums\GraduationStatus;
use App\Models\GraduationPeriod;
use App\Models\GraduationRegistration;
use App\Services\GraduationService;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Pendaftar wisuda')]
class Registrations extends Component
{
    public GraduationPeriod $period;

    #[Url(as: 'status')]
    public string $filter = '';

    public ?int $rejectingId = null;

    public string $reason = '';

    public function mount(GraduationPeriod $period): void
    {
        $this->period = $period;
    }

    public function approve(int $registrationId, GraduationService $service): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $service->decide($this->registration($registrationId), auth()->user(), true);
        $this->dispatch('toast', message: 'Pendaftaran disetujui.', type: 'success');
    }

    public function startReject(int $registrationId): void
    {
        $this->rejectingId = $registrationId;
        $this->reason = '';
    }

    public function cancelReject(): void
    {
        $this->reset(['rejectingId', 'reason']);
    }

    public function reject(GraduationService $service): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $this->validate(['reason' => ['required', 'string', 'max:255']], [], ['reason' => 'alasan penolakan']);

        $service->decide($this->registration($this->rejectingId), auth()->user(), false, $this->reason);
        $this->reset(['rejectingId', 'reason']);
        $this->dispatch('toast', message: 'Pendaftaran ditolak.', type: 'success');
    }

    private function registration(int $registrationId): GraduationRegistration
    {
        return $this->period->registrations()->with('period.minute')->findOrFail($registrationId);
    }

    public function render()
    {
        return view('livewire.wisuda.registrations', [
            'registrations' => $this->period->registrations()
                ->with('student.program')
                ->when($this->filter !== '', fn ($query) => $query->where('status', $this->filter))
                ->latest()
                ->get(),
            'canDecide' => auth()->user()->isAdmin() && ! $this->period->isFinalized(),
            'statuses' => GraduationStatus::cases(),
        ]);
    }
}
