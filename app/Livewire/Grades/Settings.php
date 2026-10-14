<?php

namespace App\Livewire\Grades;

use App\Models\AcademicTerm;
use App\Services\AuditLogger;
use App\Support\GradeScale;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Pengaturan nilai')]
class Settings extends Component
{
    /** @var array<string, int|string> */
    public array $weights = [];

    /** @var array<string, int|string> */
    public array $thresholds = [];

    /** @var array<int, string> */
    public array $remedialLetters = [];

    /** @var array<int|string, string> term_id => tanggal batas input nilai */
    public array $deadlines = [];

    public function mount(): void
    {
        $scale = GradeScale::for(auth()->user()->tenant);

        $this->weights = array_map('strval', $scale->weights);
        $this->thresholds = array_map('strval', $scale->thresholds);
        $this->remedialLetters = $scale->remedialLetters;

        foreach (AcademicTerm::query()->get() as $term) {
            $this->deadlines[$term->id] = $term->grading_closes_on?->toDateString() ?? '';
        }
    }

    public function save(AuditLogger $audit): void
    {
        $data = $this->validate([
            'weights.assignment' => ['required', 'integer', 'between:0,100'],
            'weights.midterm' => ['required', 'integer', 'between:0,100'],
            'weights.final' => ['required', 'integer', 'between:0,100'],
            'weights.attendance' => ['required', 'integer', 'between:0,100'],
            'thresholds.A' => ['required', 'integer', 'between:1,100'],
            'thresholds.B' => ['required', 'integer', 'between:1,100'],
            'thresholds.C' => ['required', 'integer', 'between:1,100'],
            'thresholds.D' => ['required', 'integer', 'between:1,100'],
            'remedialLetters' => ['array'],
            'remedialLetters.*' => ['in:C,D,E'],
            'deadlines.*' => ['nullable', 'date'],
        ], [], [
            'weights.assignment' => 'bobot tugas', 'weights.midterm' => 'bobot UTS', 'weights.final' => 'bobot UAS', 'weights.attendance' => 'bobot kehadiran',
            'thresholds.A' => 'batas A', 'thresholds.B' => 'batas B', 'thresholds.C' => 'batas C', 'thresholds.D' => 'batas D',
        ]);

        if (array_sum($data['weights']) !== 100) {
            throw ValidationException::withMessages(['weights' => 'Jumlah bobot harus 100 (sekarang '.array_sum($data['weights']).').']);
        }

        $t = array_map('intval', $data['thresholds']);

        if (! ($t['A'] > $t['B'] && $t['B'] > $t['C'] && $t['C'] > $t['D'])) {
            throw ValidationException::withMessages(['thresholds' => 'Batas nilai harus menurun: A > B > C > D.']);
        }

        $tenant = auth()->user()->tenant;
        $before = $tenant->settings ?? [];

        $tenant->update(['settings' => array_merge($before, [
            'grade_weights' => array_map('intval', $data['weights']),
            'grade_scale' => $t,
            'remedial_letters' => array_values($data['remedialLetters'] ?? []),
        ])]);

        foreach ($data['deadlines'] ?? [] as $termId => $date) {
            AcademicTerm::query()->whereKey($termId)->update(['grading_closes_on' => $date ?: null]);
        }

        $audit->log('grades.settings_updated', $tenant, [], ['weights' => $data['weights'], 'scale' => $t]);

        $this->dispatch('toast', message: 'Pengaturan nilai disimpan. Berlaku untuk perhitungan berikutnya.', type: 'success');
    }

    public function render()
    {
        return view('livewire.grades.settings', [
            'terms' => AcademicTerm::query()->orderByDesc('starts_at')->get(),
            'components' => GradeScale::COMPONENTS,
            'weightSum' => array_sum(array_map('intval', $this->weights)),
        ]);
    }
}
