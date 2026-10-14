<?php

namespace App\Livewire\Yudisium;

use App\Models\Yudisium;
use App\Services\AuditLogger;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Yudisium')]
class Index extends Component
{
    public string $name = '';

    public string $held_on = '';

    public string $number = '';

    public function create(AuditLogger $audit): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'held_on' => ['required', 'date'],
            'number' => ['nullable', 'string', 'max:60'],
        ], [], ['name' => 'nama yudisium', 'held_on' => 'tanggal yudisium', 'number' => 'nomor SK']);

        $yudisium = Yudisium::create(['tenant_id' => auth()->user()->tenant_id, 'name' => $data['name'], 'held_on' => $data['held_on'], 'number' => $data['number'] ?: null, 'status' => 'draft']);
        $audit->log('yudisium.created', $yudisium, [], $data);

        $this->reset(['name', 'held_on', 'number']);
        $this->redirectRoute('yudisium.show', $yudisium, navigate: true);
    }

    public function render()
    {
        return view('livewire.yudisium.index', [
            'yudisiums' => Yudisium::query()->withCount('decisions')->orderByDesc('held_on')->get(),
        ]);
    }
}
