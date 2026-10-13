<?php

namespace App\Livewire\Profile;

use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Profil')]
class Show extends Component
{
    public function render()
    {
        $user = auth()->user()->load('tenant');

        $student = $user->student()->with(['program.faculty', 'guardians'])->first();
        $group = $student?->currentGroup()?->load('homeroomTeacher', 'academicTerm');

        $guardian = $user->guardian()->with('students.program')->first();
        $teacher = $user->teacher;

        return view('livewire.profile.show', [
            'user' => $user,
            'student' => $student,
            'group' => $group,
            'guardian' => $guardian,
            'teacher' => $teacher,
            'parents' => $student
                ? $student->guardians->sortBy(fn ($parent) => array_search($parent->relation, ['Ayah', 'Ibu', 'Wali'], true) === false ? 9 : array_search($parent->relation, ['Ayah', 'Ibu', 'Wali'], true))->values()
                : collect(),
        ]);
    }
}
