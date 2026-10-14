<?php

namespace App\Livewire\Wisuda;

use App\Enums\GraduationStatus;
use App\Models\GraduationPeriod;
use App\Models\GraduationRegistration;
use App\Services\GraduationService;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Daftar wisuda')]
class Register extends Component
{
    public GraduationPeriod $period;

    public string $thesis_title = '';

    public string $defense_date = '';

    public string $gown_size = '';

    public function mount(GraduationPeriod $period): void
    {
        abort_unless($period->is_published && auth()->user()->student, 404);

        $this->period = $period;

        $existing = $this->existing();

        if ($existing) {
            $this->fill([
                'thesis_title' => $existing->thesis_title,
                'defense_date' => $existing->defense_date->toDateString(),
                'gown_size' => (string) $existing->gown_size,
            ]);
        }
    }

    private function existing(): ?GraduationRegistration
    {
        return GraduationRegistration::query()
            ->where('graduation_period_id', $this->period->id)
            ->where('student_id', auth()->user()->student->id)
            ->first();
    }

    public function save(GraduationService $service): mixed
    {
        $data = $this->validate([
            'thesis_title' => ['required', 'string', 'max:300'],
            'defense_date' => ['required', 'date', 'before_or_equal:today'],
            'gown_size' => ['nullable', 'in:S,M,L,XL,XXL'],
        ], [], [
            'thesis_title' => 'judul skripsi',
            'defense_date' => 'tanggal sidang', 'gown_size' => 'ukuran toga',
        ]);

        $service->register(auth()->user()->student, $this->period, [
            ...$data,
            'gown_size' => $data['gown_size'] ?: null,
        ]);

        session()->flash('toast', 'Pendaftaran wisuda dikirim.');

        return $this->redirectRoute('wisuda.overview', ['periode' => $this->period->id], navigate: true);
    }

    public function render(GraduationService $service)
    {
        $existing = $this->existing();

        return view('livewire.wisuda.register', [
            'existing' => $existing,
            'locked' => $existing !== null && $existing->status !== GraduationStatus::Rejected,
            'eligibility' => $service->eligibility(auth()->user()->student),
        ]);
    }
}
