<?php

namespace App\Livewire\Enrollment;

use App\Enums\EnrollmentStatus;
use App\Enums\Role;
use App\Models\AcademicTerm;
use App\Models\Enrollment;
use App\Models\StudyGroup;
use App\Models\StudyGroupMember;
use App\Services\EnrollmentService;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Persetujuan KRS')]
class Approvals extends Component
{
    #[Url(as: 'term')]
    public string $termId = '';

    #[Url(as: 'status')]
    public string $status = 'submitted';

    public ?int $rejectingId = null;

    public string $rejectNote = '';

    private function term(): ?AcademicTerm
    {
        return ($this->termId !== '' ? AcademicTerm::query()->find($this->termId) : null)
            ?? AcademicTerm::current()
            ?? AcademicTerm::query()->orderByDesc('starts_at')->first();
    }

    /**
     * Kelas yang boleh dikelola: semua untuk admin, Kaprodi, dan wakil dekan; kelas perwalian untuk dosen.
     *
     * @return Builder<StudyGroup>
     */
    private function groups(AcademicTerm $term)
    {
        $user = auth()->user();

        return StudyGroup::query()
            ->where('academic_term_id', $term->id)
            ->when($user->hasRole(Role::Teacher), fn ($query) => $query->where('homeroom_teacher_id', $user->teacher?->id ?? 0));
    }

    public function approve(int $enrollmentId, EnrollmentService $service): void
    {
        $service->decide(Enrollment::query()->findOrFail($enrollmentId), auth()->user(), true);

        $this->dispatch('toast', message: 'KRS disetujui.', type: 'success');
    }

    public function startReject(int $enrollmentId): void
    {
        $this->rejectingId = $enrollmentId;
        $this->rejectNote = '';
        $this->resetErrorBag();
    }

    public function cancelReject(): void
    {
        $this->reset(['rejectingId', 'rejectNote']);
    }

    public function reject(EnrollmentService $service): void
    {
        $this->validate(['rejectNote' => ['required', 'string', 'min:5', 'max:500']], [], ['rejectNote' => 'alasan penolakan']);

        $service->decide(Enrollment::query()->findOrFail($this->rejectingId), auth()->user(), false, $this->rejectNote);

        $this->cancelReject();
        $this->dispatch('toast', message: 'KRS dikembalikan ke mahasiswa.', type: 'success');
    }

    public function render()
    {
        $term = $this->term();

        if ($term === null) {
            return view('livewire.enrollment.approvals', ['term' => null]);
        }

        $groups = $this->groups($term)->get();
        $studentIds = StudyGroupMember::query()->whereIn('study_group_id', $groups->pluck('id'))->whereNull('left_at')->pluck('student_id');

        $base = Enrollment::query()->where('academic_term_id', $term->id)->whereIn('student_id', $studentIds);
        $counts = (clone $base)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $enrollments = (clone $base)
            ->with(['student', 'assignments.subject', 'assignments.studyGroup', 'decider'])
            ->when(EnrollmentStatus::tryFrom($this->status), fn ($query, $status) => $query->where('status', $status))
            ->get()
            ->sortBy(fn (Enrollment $enrollment): string => $enrollment->student->name)
            ->values();

        return view('livewire.enrollment.approvals', [
            'term' => $term,
            'terms' => AcademicTerm::query()->orderByDesc('starts_at')->get(),
            'enrollments' => $enrollments,
            'counts' => $counts,
            'notStarted' => $studentIds->count() - (clone $base)->count(),
            'statuses' => EnrollmentStatus::cases(),
            'isTeacher' => auth()->user()->hasRole(Role::Teacher),
        ]);
    }
}
