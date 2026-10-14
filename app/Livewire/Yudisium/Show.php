<?php

namespace App\Livewire\Yudisium;

use App\Models\Yudisium;
use App\Services\YudisiumService;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Detail yudisium')]
class Show extends Component
{
    #[Locked]
    public int $yudisiumId;

    /** @var array<int, string> id mahasiswa yang dicentang */
    public array $selected = [];

    public string $number = '';

    public bool $onlyEligible = false;

    public function mount(Yudisium $yudisium): void
    {
        $this->yudisiumId = $yudisium->id;
        $this->number = (string) $yudisium->number;
    }

    public function selectEligible(YudisiumService $service): void
    {
        $this->selected = $service->candidates(auth()->user()->tenant)
            ->filter(fn ($student): bool => $service->evaluate($student, $this->yudisium()->held_on)['eligible'])
            ->map(fn ($student): string => (string) $student->id)->values()->all();
    }

    private function yudisium(): Yudisium
    {
        return Yudisium::query()->findOrFail($this->yudisiumId);
    }

    public function finalize(YudisiumService $service): void
    {
        $this->validate(['number' => ['nullable', 'string', 'max:60']], [], ['number' => 'nomor SK']);

        $granted = $service->finalize($this->yudisium(), auth()->user(), array_map('intval', $this->selected), $this->number);

        $this->selected = [];
        $this->dispatch('toast', message: "{$granted} mahasiswa dinyatakan lulus.", type: 'success');
    }

    public function render(YudisiumService $service)
    {
        $yudisium = $this->yudisium();
        $rows = collect();

        if (! $yudisium->isFinalized()) {
            $rows = $service->candidates(auth()->user()->tenant)
                ->map(fn ($student): array => ['student' => $student, 'result' => $service->evaluate($student, $yudisium->held_on)])
                ->when($this->onlyEligible, fn ($all) => $all->filter(fn (array $row): bool => $row['result']['eligible']))
                ->values();
        }

        return view('livewire.yudisium.show', [
            'yudisium' => $yudisium,
            'rows' => $rows,
            'decisions' => $yudisium->decisions()->with('student.program')->get()->sortBy(fn ($decision): string => $decision->student->name)->values(),
        ]);
    }
}
