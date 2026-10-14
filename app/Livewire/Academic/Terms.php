<?php

namespace App\Livewire\Academic;

use App\Models\AcademicTerm;
use App\Services\AuditLogger;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Semester akademik')]
class Terms extends Component
{
    public ?int $editingId = null;

    public string $name = '';

    public string $semester = 'Ganjil';

    public string $starts_at = '';

    public string $ends_at = '';

    public string $grading_closes_on = '';

    public function edit(int $termId): void
    {
        $term = AcademicTerm::query()->findOrFail($termId);

        $this->editingId = $term->id;
        $this->name = $term->name;
        $this->semester = $term->semester;
        $this->starts_at = $term->starts_at->toDateString();
        $this->ends_at = $term->ends_at->toDateString();
        $this->grading_closes_on = $term->grading_closes_on?->toDateString() ?? '';
        $this->resetErrorBag();
    }

    public function cancel(): void
    {
        $this->reset(['editingId', 'name', 'starts_at', 'ends_at', 'grading_closes_on']);
        $this->semester = 'Ganjil';
        $this->resetErrorBag();
    }

    public function save(AuditLogger $audit): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'regex:/^\d{4}\/\d{4}$/'],
            'semester' => ['required', 'in:Ganjil,Genap'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'grading_closes_on' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ], ['name.regex' => 'Tahun ajaran harus berformat 2026/2027.'], [
            'name' => 'tahun ajaran', 'semester' => 'semester', 'starts_at' => 'tanggal mulai',
            'ends_at' => 'tanggal selesai', 'grading_closes_on' => 'batas input nilai',
        ]);

        $duplicate = AcademicTerm::query()
            ->where('name', $data['name'])->where('semester', $data['semester'])
            ->when($this->editingId, fn ($query) => $query->whereKeyNot($this->editingId))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages(['name' => 'Semester ini sudah terdaftar.']);
        }

        $attributes = [...$data, 'grading_closes_on' => $data['grading_closes_on'] ?: null];

        if ($this->editingId) {
            $term = AcademicTerm::query()->findOrFail($this->editingId);
            $before = $term->only(array_keys($attributes));
            $term->update($attributes);
            $audit->log('term.updated', $term, $before, $attributes);
            $message = 'Semester diperbarui.';
        } else {
            $term = AcademicTerm::create($attributes + ['tenant_id' => auth()->user()->tenant_id]);
            $audit->log('term.created', $term, [], $attributes);
            $message = 'Semester ditambahkan. Aktifkan bila sudah dimulai.';
        }

        $this->cancel();
        $this->dispatch('toast', message: $message, type: 'success');
    }

    public function activate(int $termId, AuditLogger $audit): void
    {
        $term = AcademicTerm::query()->findOrFail($termId);

        if ($term->is_locked) {
            $this->dispatch('toast', message: 'Semester terkunci. Buka kuncinya dulu sebelum diaktifkan.', type: 'warning');

            return;
        }

        $term->activate();
        $audit->log('term.activated', $term, [], ['is_active' => true]);
        $this->dispatch('toast', message: "Semester {$term->name} {$term->semester} kini aktif.", type: 'success');
    }

    public function toggleLock(int $termId, AuditLogger $audit): void
    {
        $term = AcademicTerm::query()->findOrFail($termId);

        if ($term->is_active && ! $term->is_locked) {
            $this->dispatch('toast', message: 'Semester aktif tidak bisa dikunci. Aktifkan semester lain dulu.', type: 'warning');

            return;
        }

        $term->update(['is_locked' => ! $term->is_locked]);
        $audit->log($term->is_locked ? 'term.locked' : 'term.unlocked', $term, [], ['is_locked' => $term->is_locked]);
        $this->dispatch('toast', message: $term->is_locked ? 'Semester dikunci sebagai arsip.' : 'Kunci semester dibuka.', type: 'success');
    }

    public function render()
    {
        return view('livewire.academic.terms', [
            'terms' => AcademicTerm::query()->withCount('studyGroups')->orderByDesc('starts_at')->get(),
        ]);
    }
}
