<?php

namespace App\Livewire\Groups;

use App\Models\AcademicTerm;
use App\Models\StudyGroup;
use App\Models\Teacher;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Rombel')]
class Index extends Component
{
    public bool $showForm = false;

    public string $name = '';

    public string $homeroom_teacher_id = '';

    public function save(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $term = AcademicTerm::current();
        abort_if($term === null, 422, 'Belum ada tahun ajaran aktif.');

        $data = $this->validate([
            'name' => ['required', 'string', 'max:50'],
            'homeroom_teacher_id' => ['nullable', 'exists:teachers,id'],
        ], [], ['name' => 'nama rombel', 'homeroom_teacher_id' => 'wali kelas']);

        StudyGroup::create([
            'academic_term_id' => $term->id,
            'name' => $data['name'],
            'homeroom_teacher_id' => $data['homeroom_teacher_id'] ?: null,
        ]);

        $this->reset(['showForm', 'name', 'homeroom_teacher_id']);
        $this->dispatch('toast', message: 'Rombel dibuat.', type: 'success');
    }

    public function render()
    {
        return view('livewire.groups.index', [
            'term' => AcademicTerm::current(),
            'groups' => StudyGroup::query()
                ->with('homeroomTeacher')
                ->withCount(['members as students_count' => fn ($query) => $query->whereNull('left_at')])
                ->whereHas('academicTerm', fn ($query) => $query->where('is_active', true))
                ->orderBy('name')
                ->get(),
            'teachers' => Teacher::query()->orderBy('name')->get(),
        ]);
    }
}
