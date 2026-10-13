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

    public string $gpa = '';

    public string $credits_earned = '';

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
                'gpa' => (string) $existing->gpa,
                'credits_earned' => (string) $existing->credits_earned,
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
            'gpa' => ['required', 'numeric', 'between:0,4'],
            'credits_earned' => ['required', 'integer', 'between:0,300'],
            'defense_date' => ['required', 'date', 'before_or_equal:today'],
            'gown_size' => ['nullable', 'in:S,M,L,XL,XXL'],
        ], [], [
            'thesis_title' => 'judul skripsi', 'gpa' => 'IPK', 'credits_earned' => 'SKS lulus',
            'defense_date' => 'tanggal sidang', 'gown_size' => 'ukuran toga',
        ]);

        $service->register(auth()->user()->student, $this->period, [
            ...$data,
            'gown_size' => $data['gown_size'] ?: null,
        ]);

        session()->flash('toast', 'Pendaftaran wisuda dikirim.');

        return $this->redirectRoute('wisuda.overview', ['periode' => $this->period->id], navigate: true);
    }

    public function render()
    {
        $existing = $this->existing();
        $tenant = auth()->user()->tenant;

        return view('livewire.wisuda.register', [
            'existing' => $existing,
            'locked' => $existing !== null && $existing->status !== GraduationStatus::Rejected,
            'minCredits' => (int) $tenant->setting('graduation_min_credits'),
            'minGpa' => number_format((float) $tenant->setting('graduation_min_gpa'), 2, ',', '.'),
        ]);
    }
}
