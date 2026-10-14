<?php

namespace App\Livewire\Journals;

use App\Enums\Role;
use App\Models\AttendanceSession;
use App\Models\ClassJournal;
use App\Models\TeachingAssignment;
use App\Services\EvaluationService;
use App\Services\JournalService;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Jurnal kuliah')]
class Show extends Component
{
    #[Locked]
    public int $assignmentId;

    public ?int $editingSessionId = null;

    public string $topic = '';

    public string $material = '';

    public string $notes = '';

    public function mount(TeachingAssignment $assignment, EvaluationService $evaluations): void
    {
        $user = auth()->user();

        $allowed = match (true) {
            $user->hasRole(Role::Admin, Role::Principal, Role::Curriculum) => true,
            $user->hasRole(Role::Teacher) => $assignment->teacher_id === $user->teacher?->id,
            $user->hasRole(Role::Student) => $evaluations->assignmentsFor($user->student, $assignment->academicTerm)->contains('id', $assignment->id),
            default => false,
        };

        abort_unless($allowed, 403);

        $this->assignmentId = $assignment->id;
    }

    private function isOwnerTeacher(TeachingAssignment $assignment): bool
    {
        $user = auth()->user();

        return $user->hasRole(Role::Teacher) && $assignment->teacher_id === $user->teacher?->id;
    }

    public function edit(int $sessionId, JournalService $service): void
    {
        $assignment = TeachingAssignment::query()->findOrFail($this->assignmentId);
        abort_unless($this->isOwnerTeacher($assignment), 403);

        $session = AttendanceSession::query()->with('schedule')->findOrFail($sessionId);
        abort_unless($session->schedule?->teaching_assignment_id === $assignment->id, 404);

        $journal = ClassJournal::query()->where('attendance_session_id', $session->id)->first();

        $this->editingSessionId = $session->id;
        $this->topic = (string) $journal?->topic;
        $this->material = (string) $journal?->material;
        $this->notes = (string) $journal?->notes;
        $this->resetErrorBag();
    }

    public function cancel(): void
    {
        $this->reset(['editingSessionId', 'topic', 'material', 'notes']);
        $this->resetErrorBag();
    }

    public function save(JournalService $service): void
    {
        $assignment = TeachingAssignment::query()->findOrFail($this->assignmentId);
        abort_unless($this->isOwnerTeacher($assignment), 403);

        $data = $this->validate([
            'topic' => ['required', 'string', 'max:200'],
            'material' => ['nullable', 'string', 'max:3000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [], ['topic' => 'pokok bahasan', 'material' => 'materi', 'notes' => 'catatan']);

        $session = AttendanceSession::query()->with('schedule')->findOrFail($this->editingSessionId);
        abort_unless($session->schedule?->teaching_assignment_id === $assignment->id, 404);

        $service->save($session, $assignment->teacher, $data);

        $this->cancel();
        $this->dispatch('toast', message: 'Jurnal disimpan.', type: 'success');
    }

    public function render(JournalService $service)
    {
        $assignment = TeachingAssignment::query()->with(['subject', 'teacher', 'studyGroup', 'academicTerm'])->findOrFail($this->assignmentId);
        $meetings = $service->meetings($assignment);

        return view('livewire.journals.show', [
            'assignment' => $assignment,
            'meetings' => $meetings,
            'filled' => $meetings->whereNotNull('journal')->count(),
            'canEdit' => $this->isOwnerTeacher($assignment),
            'canPrint' => ! auth()->user()->hasRole(Role::Student),
        ]);
    }
}
