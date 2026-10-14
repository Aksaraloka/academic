<?php

namespace App\Livewire\Graduation;

use App\Enums\GraduationStatus;
use App\Models\GraduationPeriod;
use App\Services\GraduationService;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Berita acara wisuda')]
class Minutes extends Component
{
    public GraduationPeriod $period;

    public string $number = '';

    public string $issued_on = '';

    public string $chair_name = '';

    public string $chair_title = 'Ketua Panitia Wisuda';

    public string $approver_name = '';

    public string $approver_title = 'Wakil Rektor Bidang Akademik';

    public string $notes = '';

    public function mount(GraduationPeriod $period, GraduationService $service): void
    {
        $this->period = $period;
        $minute = $period->minute;

        if ($minute) {
            $this->fill([
                'number' => $minute->number,
                'issued_on' => $minute->issued_on->toDateString(),
                'chair_name' => $minute->chair_name,
                'chair_title' => $minute->chair_title,
                'approver_name' => $minute->approver_name,
                'approver_title' => $minute->approver_title,
                'notes' => (string) $minute->notes,
            ]);

            return;
        }

        $this->number = $service->suggestNumber($period);
        $this->issued_on = today()->toDateString();
    }

    public function finalize(GraduationService $service): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $data = $this->validate([
            'number' => ['required', 'string', 'max:100'],
            'issued_on' => ['required', 'date'],
            'chair_name' => ['required', 'string', 'max:150'],
            'chair_title' => ['required', 'string', 'max:150'],
            'approver_name' => ['required', 'string', 'max:150'],
            'approver_title' => ['required', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'number' => 'nomor berita acara', 'issued_on' => 'tanggal terbit', 'chair_name' => 'nama ketua panitia',
            'chair_title' => 'jabatan ketua panitia', 'approver_name' => 'nama pejabat pengesah', 'approver_title' => 'jabatan pejabat pengesah',
        ]);

        $service->finalizeMinutes($this->period, auth()->user(), [...$data, 'notes' => $data['notes'] ?: null]);

        $this->period->load('minute');
        $this->dispatch('toast', message: 'Berita acara diterbitkan.', type: 'success');
    }

    public function render()
    {
        $minute = $this->period->minute;
        $finalized = $minute?->finalized_at !== null;

        $participants = $finalized
            ? collect($minute->participants)
            : $this->period->registrations()->with('student.program')->where('status', GraduationStatus::Approved)->get()
                ->map(fn ($registration): array => [
                    'name' => $registration->student->name,
                    'nim' => $registration->student->nim,
                    'program' => $registration->student->program?->label() ?? '-',
                    'gpa' => number_format((float) $registration->gpa, 2, ',', '.'),
                    'thesis_title' => $registration->thesis_title,
                ]);

        return view('livewire.graduation.minutes', [
            'finalized' => $finalized,
            'minute' => $minute,
            'participants' => $participants,
            'pending' => $this->period->registrations()->where('status', GraduationStatus::Submitted)->count(),
            'canEdit' => auth()->user()->isAdmin() && ! $finalized,
        ]);
    }
}
