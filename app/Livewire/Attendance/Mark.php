<?php

namespace App\Livewire\Attendance;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceSession;
use App\Models\ClassJournal;
use App\Models\Student;
use App\Services\AttendanceService;
use App\Services\JournalService;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Isi presensi')]
class Mark extends Component
{
    public AttendanceSession $session;

    /** @var array<int, string> student_id => status */
    public array $statuses = [];

    /** @var array<int, string> */
    public array $notes = [];

    public string $journalTopic = '';

    public string $journalMaterial = '';

    public string $journalNotes = '';

    public function mount(AttendanceSession $session): void
    {
        $this->session = $session->load('studyGroup', 'schedule.assignment.subject', 'schedule.timeSlot');

        $records = $session->records()->get();
        $this->statuses = $records->mapWithKeys(fn ($record) => [$record->student_id => $record->status->value])->all();
        $this->notes = $records->mapWithKeys(fn ($record) => [$record->student_id => (string) $record->note])->all();

        $journal = ClassJournal::query()->where('attendance_session_id', $session->id)->first();
        $this->journalTopic = (string) $journal?->topic;
        $this->journalMaterial = (string) $journal?->material;
        $this->journalNotes = (string) $journal?->notes;
    }

    /**
     * Ketuk mahasiswa: Hadir menjadi Alpa, status lain kembali ke Hadir (P-02).
     */
    public function toggleAbsent(int $studentId): void
    {
        if (! isset($this->statuses[$studentId])) {
            return;
        }

        $this->statuses[$studentId] = $this->statuses[$studentId] === AttendanceStatus::Present->value
            ? AttendanceStatus::Absent->value
            : AttendanceStatus::Present->value;
    }

    public function setStatus(int $studentId, string $status): void
    {
        if (isset($this->statuses[$studentId]) && AttendanceStatus::tryFrom($status)) {
            $this->statuses[$studentId] = $status;
        }
    }

    public function markAllPresent(): void
    {
        foreach ($this->statuses as $studentId => $status) {
            $this->statuses[$studentId] = AttendanceStatus::Present->value;
        }
    }

    /**
     * Simpan jurnal perkuliahan bersama presensi. Saat menyelesaikan sesi kuliah, pokok bahasan wajib diisi.
     */
    private function saveJournal(JournalService $journals, AttendanceService $service, bool $required): void
    {
        if ($this->session->schedule === null || ! $service->canEdit($this->session, auth()->user())) {
            return;
        }

        if (! $required && trim($this->journalTopic) === '') {
            return;
        }

        $data = $this->validate([
            'journalTopic' => ['required', 'string', 'max:200'],
            'journalMaterial' => ['nullable', 'string', 'max:3000'],
            'journalNotes' => ['nullable', 'string', 'max:1000'],
        ], [], ['journalTopic' => 'pokok bahasan', 'journalMaterial' => 'materi', 'journalNotes' => 'catatan']);

        $journals->save($this->session, $this->session->schedule->assignment->teacher, ['topic' => $data['journalTopic'], 'material' => $data['journalMaterial'], 'notes' => $data['journalNotes']]);
    }

    public function save(AttendanceService $service, JournalService $journals): void
    {
        $this->saveJournal($journals, $service, false);
        $service->saveStatuses($this->session, auth()->user(), $this->statuses, $this->notes);
        $this->dispatch('toast', message: 'Presensi disimpan.', type: 'success');
    }

    public function finish(AttendanceService $service, JournalService $journals): mixed
    {
        $this->saveJournal($journals, $service, true);
        $service->saveStatuses($this->session, auth()->user(), $this->statuses, $this->notes);
        $service->closeSession($this->session, auth()->user());

        return $this->redirectRoute('attendance.index', navigate: true);
    }

    public function render(AttendanceService $service)
    {
        $students = Student::query()->whereIn('id', array_keys($this->statuses))->orderBy('name')->get()->keyBy('id');
        $counts = collect($this->statuses)->countBy();

        return view('livewire.attendance.mark', [
            'students' => $students,
            'counts' => $counts,
            'canEdit' => $service->canEdit($this->session, auth()->user()),
            'journal' => ClassJournal::query()->where('attendance_session_id', $this->session->id)->first(),
            'levelStatuses' => AttendanceStatus::forLevel(auth()->user()->tenant->level),
        ]);
    }
}
