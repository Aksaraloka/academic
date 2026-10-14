<?php

namespace App\Livewire\Advising;

use App\Enums\Role;
use App\Models\AcademicTerm;
use App\Models\AdvisingNote;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\StudyGroup;
use App\Models\StudyGroupMember;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Perwalian')]
class Index extends Component
{
    #[Url(as: 'student')]
    public ?int $studentId = null;

    #[Url(as: 'search')]
    public string $search = '';

    public ?int $editingId = null;

    public string $held_on = '';

    public string $topic = '';

    public string $notes = '';

    public string $follow_up = '';

    public function mount(): void
    {
        $this->held_on = today()->toDateString();
    }

    /**
     * Dosen hanya melihat mahasiswa perwaliannya; admin, Kaprodi, dan wakil dekan melihat semuanya.
     *
     * @return array<int, int>
     */
    private function visibleStudentIds(AcademicTerm $term): array
    {
        $user = auth()->user();

        $groups = StudyGroup::query()
            ->where('academic_term_id', $term->id)
            ->when($user->hasRole(Role::Teacher), fn ($query) => $query->where('homeroom_teacher_id', $user->teacher?->id ?? 0))
            ->pluck('id');

        return StudyGroupMember::query()->whereIn('study_group_id', $groups)->whereNull('left_at')->pluck('student_id')->all();
    }

    private function isAdvisorOf(Student $student, AcademicTerm $term): bool
    {
        $user = auth()->user();

        return $user->hasRole(Role::Teacher) && $user->teacher !== null
            && StudyGroup::query()
                ->where('academic_term_id', $term->id)->where('homeroom_teacher_id', $user->teacher->id)
                ->whereHas('members', fn ($query) => $query->where('student_id', $student->id)->whereNull('left_at'))
                ->exists();
    }

    public function select(int $studentId): void
    {
        $this->studentId = $studentId;
        $this->cancelEdit();
    }

    public function edit(int $noteId): void
    {
        $note = AdvisingNote::query()->findOrFail($noteId);
        $term = AcademicTerm::current();

        abort_unless($term && $this->isAdvisorOf($note->student, $term) && $note->teacher_id === auth()->user()->teacher->id, 403);

        $this->editingId = $note->id;
        $this->held_on = $note->held_on->toDateString();
        $this->topic = $note->topic;
        $this->notes = $note->notes;
        $this->follow_up = (string) $note->follow_up;
    }

    public function cancelEdit(): void
    {
        $this->reset(['editingId', 'topic', 'notes', 'follow_up']);
        $this->held_on = today()->toDateString();
        $this->resetErrorBag();
    }

    public function saveNote(): void
    {
        $term = AcademicTerm::current();
        $student = $this->studentId ? Student::query()->find($this->studentId) : null;

        abort_unless($term && $student && $this->isAdvisorOf($student, $term), 403);

        $data = $this->validate([
            'held_on' => ['required', 'date', 'before_or_equal:today'],
            'topic' => ['required', 'string', 'max:150'],
            'notes' => ['required', 'string', 'min:5', 'max:3000'],
            'follow_up' => ['nullable', 'string', 'max:1000'],
        ], [], ['held_on' => 'tanggal bimbingan', 'topic' => 'topik', 'notes' => 'catatan', 'follow_up' => 'tindak lanjut']);

        $attributes = [...$data, 'follow_up' => $data['follow_up'] ?: null];

        if ($this->editingId) {
            AdvisingNote::query()->where('teacher_id', auth()->user()->teacher->id)->findOrFail($this->editingId)->update($attributes);
        } else {
            AdvisingNote::create($attributes + [
                'tenant_id' => $student->tenant_id, 'student_id' => $student->id, 'teacher_id' => auth()->user()->teacher->id, 'academic_term_id' => $term->id,
            ]);
        }

        $this->cancelEdit();
        $this->dispatch('toast', message: 'Catatan bimbingan disimpan.', type: 'success');
    }

    public function render()
    {
        $term = AcademicTerm::current();

        if ($term === null) {
            return view('livewire.advising.index', ['term' => null]);
        }

        $ids = $this->visibleStudentIds($term);
        $keyword = trim($this->search);

        $students = Student::query()
            ->whereIn('id', $ids)
            ->when($keyword !== '', fn ($query) => $query->where(fn ($inner) => $inner->where('name', 'like', "%{$keyword}%")->orWhere('nim', 'like', "%{$keyword}%")))
            ->withCount('advisingNotes')
            ->orderBy('name')
            ->get();

        $selected = $this->studentId && in_array($this->studentId, $ids, true) ? Student::query()->find($this->studentId) : null;

        return view('livewire.advising.index', [
            'term' => $term,
            'students' => $students,
            'student' => $selected,
            'history' => $selected ? AdvisingNote::query()->with(['teacher', 'academicTerm'])->where('student_id', $selected->id)->orderByDesc('held_on')->orderByDesc('id')->get() : collect(),
            'enrollment' => $selected ? Enrollment::query()->where('academic_term_id', $term->id)->where('student_id', $selected->id)->first() : null,
            'canWrite' => $selected && $this->isAdvisorOf($selected, $term),
            'myTeacherId' => auth()->user()->teacher?->id,
        ]);
    }
}
