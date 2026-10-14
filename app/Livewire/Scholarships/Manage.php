<?php

namespace App\Livewire\Scholarships;

use App\Models\Scholarship;
use App\Services\AuditLogger;
use App\Services\ScholarshipService;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Kelola beasiswa')]
class Manage extends Component
{
    public ?int $editingId = null;

    public bool $formOpen = false;

    public string $name = '';

    public string $provider = '';

    public string $description = '';

    public string $requirements = '';

    public string $amount_per_term = '';

    public string $quota = '';

    public string $min_gpa = '';

    public string $min_semester = '';

    public string $opens_on = '';

    public string $closes_on = '';

    public bool $is_active = true;

    public function mount(ScholarshipService $service): void
    {
        abort_unless($service->isManager(auth()->user()), 403);
    }

    public function create(): void
    {
        $this->resetForm();
        $this->opens_on = today()->toDateString();
        $this->closes_on = today()->addMonth()->toDateString();
        $this->formOpen = true;
    }

    public function edit(int $id): void
    {
        $scholarship = Scholarship::query()->findOrFail($id);

        $this->resetForm();
        $this->editingId = $scholarship->id;
        $this->name = $scholarship->name;
        $this->provider = $scholarship->provider;
        $this->description = (string) $scholarship->description;
        $this->requirements = (string) $scholarship->requirements;
        $this->amount_per_term = (string) $scholarship->amount_per_term;
        $this->quota = (string) $scholarship->quota;
        $this->min_gpa = $scholarship->min_gpa === null ? '' : (string) $scholarship->min_gpa;
        $this->min_semester = (string) $scholarship->min_semester;
        $this->opens_on = $scholarship->opens_on->toDateString();
        $this->closes_on = $scholarship->closes_on->toDateString();
        $this->is_active = $scholarship->is_active;
        $this->formOpen = true;
    }

    public function save(AuditLogger $audit): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'provider' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:3000'],
            'requirements' => ['nullable', 'string', 'max:2000'],
            'amount_per_term' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'quota' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'min_gpa' => ['nullable', 'numeric', 'between:0,4'],
            'min_semester' => ['nullable', 'integer', 'between:1,14'],
            'opens_on' => ['required', 'date'],
            'closes_on' => ['required', 'date', 'after_or_equal:opens_on'],
            'is_active' => ['boolean'],
        ], [], [
            'name' => 'nama beasiswa', 'provider' => 'penyelenggara', 'description' => 'deskripsi', 'requirements' => 'persyaratan lain',
            'amount_per_term' => 'besaran', 'quota' => 'kuota', 'min_gpa' => 'IPK minimal', 'min_semester' => 'semester minimal',
            'opens_on' => 'tanggal buka', 'closes_on' => 'tanggal tutup',
        ]);

        $values = [
            'name' => trim($data['name']), 'provider' => trim($data['provider']),
            'description' => filled($data['description']) ? trim($data['description']) : null,
            'requirements' => filled($data['requirements']) ? trim($data['requirements']) : null,
            'amount_per_term' => filled($data['amount_per_term']) ? (int) $data['amount_per_term'] : null,
            'quota' => filled($data['quota']) ? (int) $data['quota'] : null,
            'min_gpa' => filled($data['min_gpa']) ? (float) $data['min_gpa'] : null,
            'min_semester' => filled($data['min_semester']) ? (int) $data['min_semester'] : null,
            'opens_on' => $data['opens_on'], 'closes_on' => $data['closes_on'], 'is_active' => $data['is_active'],
        ];

        if ($this->editingId) {
            $scholarship = Scholarship::query()->findOrFail($this->editingId);
            $scholarship->update($values);
            $audit->logChanges('scholarship.updated', $scholarship);
        } else {
            $scholarship = Scholarship::create($values + ['tenant_id' => auth()->user()->tenant_id]);
            $audit->log('scholarship.created', $scholarship, [], ['name' => $scholarship->name]);
        }

        $this->resetForm();
        $this->dispatch('toast', message: 'Beasiswa disimpan.', type: 'success');
    }

    public function toggle(int $id, AuditLogger $audit): void
    {
        $scholarship = Scholarship::query()->findOrFail($id);
        $scholarship->update(['is_active' => ! $scholarship->is_active]);
        $audit->logChanges('scholarship.toggled', $scholarship);
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'formOpen', 'name', 'provider', 'description', 'requirements', 'amount_per_term', 'quota', 'min_gpa', 'min_semester', 'opens_on', 'closes_on']);
        $this->is_active = true;
        $this->resetErrorBag();
    }

    public function render()
    {
        return view('livewire.scholarships.manage', [
            'scholarships' => Scholarship::query()->withCount([
                'applications',
                'applications as accepted_count' => fn ($query) => $query->where('status', 'accepted'),
            ])->latest('closes_on')->get(),
        ]);
    }
}
