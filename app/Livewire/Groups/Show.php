<?php

namespace App\Livewire\Groups;

use App\Models\GradeLevel;
use App\Models\Student;
use App\Models\StudyGroup;
use App\Models\StudyGroupMember;
use App\Models\Teacher;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Kelas')]
class Show extends Component
{
    public StudyGroup $group;

    /** @var array<int, int|string> */
    public array $selected = [];

    public string $search = '';

    public string $homeroom_teacher_id = '';

    public string $grade_level_id = '';

    public function mount(StudyGroup $group): void
    {
        $this->group = $group;
        $this->homeroom_teacher_id = (string) ($group->homeroom_teacher_id ?? '');
        $this->grade_level_id = (string) ($group->grade_level_id ?? '');
    }

    public function saveSettings(): void
    {
        $this->authorizeManage();

        $data = $this->validate([
            'homeroom_teacher_id' => ['nullable', 'exists:teachers,id'],
            'grade_level_id' => ['nullable', 'exists:grade_levels,id'],
        ], [], ['homeroom_teacher_id' => 'dosen wali', 'grade_level_id' => 'tingkat']);

        // Per model agar pengamat model menerbitkan event untuk dosen wali yang berganti.
        $this->group->update([
            'homeroom_teacher_id' => $data['homeroom_teacher_id'] ?: null,
            'grade_level_id' => $data['grade_level_id'] ?: null,
        ]);

        $this->group->load('homeroomTeacher');
        $this->dispatch('toast', message: 'Pengaturan kelas disimpan.', type: 'success');
    }

    public function place(): void
    {
        $this->authorizeManage();

        $ids = Student::query()->whereIn('id', $this->selected)->where('status', 'aktif')->pluck('id');

        foreach ($ids as $studentId) {
            $this->leaveCurrentGroup($studentId);
            $this->group->members()->create(['student_id' => $studentId, 'joined_at' => today()]);
        }

        $this->reset('selected');
        $this->dispatch('toast', message: $ids->count().' mahasiswa ditempatkan.', type: 'success');
    }

    public function remove(int $studentId): void
    {
        $this->authorizeManage();

        // Per model (bukan query massal) agar pengamat model dapat memberi tahu LMS bahwa peserta kelas berubah.
        $this->group->members()->where('student_id', $studentId)->whereNull('left_at')->get()->each->update(['left_at' => today()]);
        $this->dispatch('toast', message: 'Mahasiswa dikeluarkan dari kelas. Riwayat tetap tersimpan.', type: 'success');
    }

    /**
     * Pindahkan anggota aktif ke kelas lain pada semester yang sama.
     */
    public function move(int $studentId, string $targetGroupId): void
    {
        $this->authorizeManage();

        if ($targetGroupId === '') {
            return;
        }

        $target = StudyGroup::query()
            ->where('academic_term_id', $this->group->academic_term_id)
            ->whereKeyNot($this->group->id)
            ->findOrFail((int) $targetGroupId);

        $member = $this->group->members()->where('student_id', $studentId)->whereNull('left_at')->first();
        abort_if($member === null, 404);

        $this->leaveCurrentGroup($studentId);
        $target->members()->create(['student_id' => $studentId, 'joined_at' => today()]);

        $this->dispatch('toast', message: "Mahasiswa dipindahkan ke {$target->name}.", type: 'success');
    }

    private function authorizeManage(): void
    {
        abort_unless(auth()->user()->canManageStudyGroups(), 403);
    }

    private function leaveCurrentGroup(int $studentId): void
    {
        StudyGroupMember::query()
            ->where('student_id', $studentId)
            ->whereNull('left_at')
            ->whereHas('studyGroup', fn ($query) => $query->where('academic_term_id', $this->group->academic_term_id))
            ->get()
            ->each->update(['left_at' => today()]);
    }

    public function render()
    {
        $placedIds = StudyGroupMember::query()
            ->whereNull('left_at')
            ->whereHas('studyGroup', fn ($query) => $query->where('academic_term_id', $this->group->academic_term_id))
            ->pluck('student_id');

        $term = $this->group->academicTerm;
        $members = $this->group->activeStudents()->orderBy('students.name')->get();

        return view('livewire.groups.show', [
            'members' => $members,
            'newIds' => Student::query()->newIn($term)->whereIn('id', $members->pluck('id'))->pluck('id')->all(),
            'otherGroups' => StudyGroup::query()
                ->where('academic_term_id', $this->group->academic_term_id)
                ->whereKeyNot($this->group->id)
                ->when($this->group->program_id, fn ($query, $programId) => $query->where('program_id', $programId))
                ->orderBy('name')->get(['id', 'name']),
            'teachers' => Teacher::query()->orderBy('name')->get(['id', 'name']),
            'gradeLevels' => GradeLevel::query()->orderBy('position')->get(['id', 'name']),
            'unplaced' => Student::query()
                ->where('status', 'aktif')
                ->whereNotIn('id', $placedIds)
                ->when($this->search !== '', fn ($query) => $query->where('name', 'like', "%{$this->search}%"))
                ->orderBy('name')
                ->limit(100)
                ->get(),
        ]);
    }
}
