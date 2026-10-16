<?php

namespace App\Livewire\Groups;

use App\Models\Student;
use App\Models\StudyGroup;
use App\Models\StudyGroupMember;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Kelas')]
class Show extends Component
{
    public StudyGroup $group;

    /** @var array<int, int|string> */
    public array $selected = [];

    public string $search = '';

    public function mount(StudyGroup $group): void
    {
        $this->group = $group;
    }

    public function place(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

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
        abort_unless(auth()->user()->isAdmin(), 403);

        // Per model (bukan query massal) agar pengamat model dapat memberi tahu LMS bahwa peserta kelas berubah.
        $this->group->members()->where('student_id', $studentId)->whereNull('left_at')->get()->each->update(['left_at' => today()]);
        $this->dispatch('toast', message: 'Mahasiswa dikeluarkan dari kelas. Riwayat tetap tersimpan.', type: 'success');
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

        return view('livewire.groups.show', [
            'members' => $this->group->activeStudents()->orderBy('students.name')->get(),
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
