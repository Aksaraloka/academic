<?php

namespace App\Livewire\Grades;

use App\Enums\GradeRevisionStatus;
use App\Enums\Role;
use App\Models\GradeRevision;
use App\Services\GradingService;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Revisi nilai')]
class Revisions extends Component
{
    #[Url(as: 'status')]
    public string $filter = '';

    public ?int $rejectingId = null;

    public string $note = '';

    public function approve(int $revisionId, GradingService $grading): void
    {
        $grading->decideRevision($this->visible()->findOrFail($revisionId), auth()->user(), true);

        $this->dispatch('toast', message: 'Revisi disetujui dan nilai diperbarui.', type: 'success');
    }

    public function startReject(int $revisionId): void
    {
        $this->rejectingId = $revisionId;
        $this->note = '';
    }

    public function cancelReject(): void
    {
        $this->reset(['rejectingId', 'note']);
    }

    public function reject(GradingService $grading): void
    {
        $grading->decideRevision($this->visible()->findOrFail($this->rejectingId), auth()->user(), false, $this->note);

        $this->cancelReject();
        $this->dispatch('toast', message: 'Revisi ditolak.', type: 'success');
    }

    /**
     * Dosen hanya melihat pengajuannya sendiri; admin dan Kaprodi melihat semuanya.
     */
    private function visible()
    {
        $user = auth()->user();

        return GradeRevision::query()
            ->with(['grade.student', 'grade.assignment.subject', 'grade.assignment.studyGroup', 'grade.assignment.academicTerm', 'requester', 'decider'])
            ->when(! $user->hasRole(Role::Admin, Role::Principal), fn ($query) => $query->where('requested_by', $user->id));
    }

    public function render()
    {
        $user = auth()->user();

        return view('livewire.grades.revisions', [
            'revisions' => $this->visible()
                ->when(GradeRevisionStatus::tryFrom($this->filter), fn ($query, GradeRevisionStatus $status) => $query->where('status', $status))
                ->orderByRaw("case when status = 'menunggu' then 0 else 1 end")
                ->latest()
                ->get(),
            'canDecide' => $user->hasRole(Role::Principal),
            'statuses' => GradeRevisionStatus::cases(),
            'pendingCount' => $this->visible()->where('status', GradeRevisionStatus::Pending)->count(),
        ]);
    }
}
