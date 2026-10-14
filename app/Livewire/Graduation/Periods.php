<?php

namespace App\Livewire\Graduation;

use App\Models\GraduationPeriod;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Periode wisuda')]
class Periods extends Component
{
    public string $name = '';

    public string $registration_opens_on = '';

    public string $registration_closes_on = '';

    public string $ceremony_on = '';

    public string $venue = '';

    public string $quota = '';

    public bool $is_published = false;

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'registration_opens_on' => ['required', 'date'],
            'registration_closes_on' => ['required', 'date', 'after_or_equal:registration_opens_on'],
            'ceremony_on' => ['required', 'date', 'after:registration_closes_on'],
            'venue' => ['nullable', 'string', 'max:255'],
            'quota' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'is_published' => ['boolean'],
        ], [], [
            'name' => 'nama periode', 'registration_opens_on' => 'tanggal buka pendaftaran',
            'registration_closes_on' => 'tanggal tutup pendaftaran', 'ceremony_on' => 'tanggal wisuda',
            'venue' => 'tempat', 'quota' => 'kuota',
        ]);

        GraduationPeriod::create([
            ...$data,
            'venue' => $data['venue'] ?: null,
            'quota' => $data['quota'] ?: null,
        ]);

        $this->reset(['name', 'registration_opens_on', 'registration_closes_on', 'ceremony_on', 'venue', 'quota', 'is_published']);
        $this->dispatch('toast', message: 'Periode wisuda dibuat.', type: 'success');
    }

    public function togglePublished(int $periodId): void
    {
        $period = GraduationPeriod::query()->findOrFail($periodId);

        if ($period->isFinalized() && $period->is_published) {
            $this->dispatch('toast', message: 'Periode dengan berita acara terbit tidak bisa disembunyikan.', type: 'warning');

            return;
        }

        $period->update(['is_published' => ! $period->is_published]);
    }

    public function render()
    {
        return view('livewire.graduation.periods', [
            'periods' => GraduationPeriod::query()->with('minute')->withCount('registrations')->orderByDesc('ceremony_on')->get(),
        ]);
    }
}
