<?php

namespace App\Livewire\Exams;

use App\Enums\ExamType;
use App\Enums\Role;
use App\Models\AcademicTerm;
use App\Models\ExamSchedule;
use App\Models\Room;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Services\AuditLogger;
use App\Services\ExamService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Jadwal ujian')]
class Index extends Component
{
    #[Url(as: 'term')]
    public string $termId = '';

    #[Url(as: 'type')]
    public string $typeFilter = '';

    public ?int $editingId = null;

    public string $type = 'midterm';

    public string $subjectId = '';

    public string $date = '';

    public string $starts_at = '08:00';

    public string $ends_at = '09:40';

    public string $roomId = '';

    public string $notes = '';

    private function canManage(): bool
    {
        return auth()->user()->hasRole(Role::Admin, Role::Curriculum);
    }

    private function term(): ?AcademicTerm
    {
        return ($this->termId !== '' ? AcademicTerm::query()->find($this->termId) : null)
            ?? AcademicTerm::current()
            ?? AcademicTerm::query()->orderByDesc('starts_at')->first();
    }

    public function edit(int $examId): void
    {
        abort_unless($this->canManage(), 403);

        $exam = ExamSchedule::query()->with('assignment')->findOrFail($examId);

        $this->editingId = $exam->id;
        $this->type = $exam->type->value;
        $this->subjectId = (string) $exam->assignment->subject_id;
        $this->date = $exam->date->toDateString();
        $this->starts_at = substr($exam->starts_at, 0, 5);
        $this->ends_at = substr($exam->ends_at, 0, 5);
        $this->roomId = (string) $exam->room_id;
        $this->notes = (string) $exam->notes;
        $this->resetErrorBag();
    }

    public function cancel(): void
    {
        $this->reset(['editingId', 'subjectId', 'date', 'roomId', 'notes']);
        $this->starts_at = '08:00';
        $this->ends_at = '09:40';
        $this->resetErrorBag();
    }

    public function save(ExamService $service, AuditLogger $audit): void
    {
        abort_unless($this->canManage(), 403);

        $term = $this->term();
        abort_if($term === null, 404);

        $data = $this->validate([
            'type' => ['required', Rule::enum(ExamType::class)],
            'subjectId' => ['required', 'exists:subjects,id'],
            'date' => ['required', 'date'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
            'roomId' => ['nullable', 'exists:rooms,id'],
            'notes' => ['nullable', 'string', 'max:200'],
        ], [], ['type' => 'jenis ujian', 'subjectId' => 'mata kuliah', 'date' => 'tanggal', 'starts_at' => 'jam mulai', 'ends_at' => 'jam selesai', 'roomId' => 'ruang', 'notes' => 'catatan']);

        $type = ExamType::from($data['type']);
        $assignments = TeachingAssignment::query()->with(['studyGroup', 'subject'])
            ->where('academic_term_id', $term->id)->where('subject_id', $data['subjectId'])->get();

        if ($this->editingId) {
            $exam = ExamSchedule::query()->findOrFail($this->editingId);
            $assignments = $assignments->where('id', $exam->teaching_assignment_id);
        } else {
            $taken = ExamSchedule::query()->where('type', $type)->whereIn('teaching_assignment_id', $assignments->pluck('id'))->pluck('teaching_assignment_id');
            $assignments = $assignments->reject(fn (TeachingAssignment $assignment): bool => $taken->contains($assignment->id));
        }

        if ($assignments->isEmpty()) {
            throw ValidationException::withMessages(['subjectId' => $this->editingId ? 'Ujian tidak ditemukan.' : 'Mata kuliah ini belum punya kelas semester ini, atau semua kelasnya sudah dijadwalkan untuk '.$type->label().'.']);
        }

        $date = now()->parse($data['date']);
        $roomId = $data['roomId'] !== '' ? (int) $data['roomId'] : null;

        $service->validate($term, $type, $date, $data['starts_at'], $data['ends_at'], $roomId, $assignments, $this->editingId);

        $attributes = ['date' => $date, 'starts_at' => $data['starts_at'], 'ends_at' => $data['ends_at'], 'room_id' => $roomId, 'notes' => $data['notes'] ?: null];

        if ($this->editingId) {
            $exam->update($attributes + ['type' => $type]);
            $audit->log('exam.updated', $exam, [], $attributes);
        } else {
            foreach ($assignments as $assignment) {
                $exam = ExamSchedule::create($attributes + [
                    'tenant_id' => $term->tenant_id, 'academic_term_id' => $term->id, 'teaching_assignment_id' => $assignment->id, 'type' => $type,
                ]);
                $audit->log('exam.created', $exam, [], $attributes);
            }
        }

        $count = $assignments->count();
        $this->cancel();
        $this->dispatch('toast', message: $count > 1 ? "{$count} jadwal ujian dibuat." : 'Jadwal ujian disimpan.', type: 'success');
    }

    public function delete(int $examId, AuditLogger $audit): void
    {
        abort_unless($this->canManage(), 403);

        $exam = ExamSchedule::query()->findOrFail($examId);
        $audit->log('exam.deleted', $exam, $exam->only(['type', 'date', 'teaching_assignment_id']));
        $exam->delete();

        $this->cancel();
        $this->dispatch('toast', message: 'Jadwal ujian dihapus.', type: 'success');
    }

    public function render()
    {
        $term = $this->term();
        $user = auth()->user();

        if ($term === null) {
            return view('livewire.exams.index', ['term' => null, 'canManage' => $this->canManage()]);
        }

        $exams = ExamSchedule::query()->with(['assignment.subject', 'assignment.studyGroup', 'assignment.teacher', 'room'])
            ->where('academic_term_id', $term->id)
            ->when(ExamType::tryFrom($this->typeFilter), fn ($query, $type) => $query->where('type', $type))
            ->when($user->hasRole(Role::Teacher), fn ($query) => $query->whereHas('assignment', fn ($inner) => $inner->where('teacher_id', $user->teacher?->id ?? 0)))
            ->orderBy('date')->orderBy('starts_at')
            ->get();

        return view('livewire.exams.index', [
            'term' => $term,
            'terms' => AcademicTerm::query()->orderByDesc('starts_at')->get(),
            'byDate' => $exams->groupBy(fn (ExamSchedule $exam): string => $exam->date->toDateString()),
            'types' => ExamType::cases(),
            'subjects' => Subject::query()->whereIn('id', TeachingAssignment::query()->where('academic_term_id', $term->id)->select('subject_id'))->orderBy('name')->get(),
            'rooms' => Room::query()->orderBy('name')->get(),
            'canManage' => $this->canManage(),
        ]);
    }
}
