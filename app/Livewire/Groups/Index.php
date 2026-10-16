<?php

namespace App\Livewire\Groups;

use App\Models\AcademicTerm;
use App\Models\GradeLevel;
use App\Models\Program;
use App\Models\Student;
use App\Models\StudyGroup;
use App\Models\Teacher;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Kelas')]
class Index extends Component
{
    public bool $showForm = false;

    public string $name = '';

    public string $homeroom_teacher_id = '';

    public string $program_id = '';

    public string $grade_level_id = '';

    /** Kosong = semua kelas; `tanpa-wali` = hanya kelas tanpa dosen wali. */
    public string $filter = '';

    public function save(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $term = AcademicTerm::current();
        abort_if($term === null, 422, 'Belum ada tahun ajaran aktif.');

        $data = $this->validate([
            'name' => ['required', 'string', 'max:50'],
            'homeroom_teacher_id' => ['nullable', 'exists:teachers,id'],
            'program_id' => ['nullable', 'exists:programs,id'],
            'grade_level_id' => ['nullable', 'exists:grade_levels,id'],
        ], [], ['name' => 'nama kelas', 'homeroom_teacher_id' => 'dosen wali', 'program_id' => 'program studi', 'grade_level_id' => 'tingkat']);

        StudyGroup::create([
            'academic_term_id' => $term->id,
            'name' => $data['name'],
            'homeroom_teacher_id' => $data['homeroom_teacher_id'] ?: null,
            'program_id' => $data['program_id'] ?: null,
            'grade_level_id' => $data['grade_level_id'] ?: null,
        ]);

        $this->reset(['showForm', 'name', 'homeroom_teacher_id', 'program_id', 'grade_level_id']);
        $this->dispatch('toast', message: 'Kelas dibuat.', type: 'success');
    }

    public function render()
    {
        $term = AcademicTerm::current();

        $groups = StudyGroup::query()
            ->with(['homeroomTeacher', 'program'])
            ->withCount([
                'members as students_count' => fn ($query) => $query->whereNull('left_at'),
                'members as new_students_count' => fn ($query) => $query->whereNull('left_at')
                    ->whereIn('student_id', $term === null ? [] : Student::query()->newIn($term)->select('id')),
            ])
            ->whereHas('academicTerm', fn ($query) => $query->where('is_active', true))
            ->orderBy('name')
            ->get();

        return view('livewire.groups.index', [
            'term' => $term,
            'groups' => $this->filter === 'tanpa-wali' ? $groups->whereNull('homeroom_teacher_id') : $groups,
            'withoutHomeroomCount' => $groups->whereNull('homeroom_teacher_id')->count(),
            'teachers' => Teacher::query()->orderBy('name')->get(),
            'programs' => Program::query()->orderBy('name')->get(),
            'gradeLevels' => GradeLevel::query()->orderBy('position')->get(),
        ]);
    }
}
