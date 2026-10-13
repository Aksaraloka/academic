<?php

namespace App\Livewire\Attendance;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceSession;
use App\Services\AttendanceService;
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

    public function mount(AttendanceSession $session): void
    {
        $this->session = $session->load('studyGroup', 'schedule.assignment.subject', 'schedule.timeSlot');

        $records = $session->records()->get();
        $this->statuses = $records->mapWithKeys(fn ($record) => [$record->student_id => $record->status->value])->all();
        $this->notes = $records->mapWithKeys(fn ($record) => [$record->student_id => (string) $record->note])->all();
    }

    /**
     * Ketuk siswa: Hadir menjadi Alpa, status lain kembali ke Hadir (P-02).
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

    public function save(AttendanceService $service): void
    {
        $service->saveStatuses($this->session, auth()->user(), $this->statuses, $this->notes);
        $this->dispatch('toast', message: 'Presensi disimpan.', type: 'success');
    }

    public function finish(AttendanceService $service): mixed
    {
        $service->saveStatuses($this->session, auth()->user(), $this->statuses, $this->notes);
        $service->closeSession($this->session, auth()->user());

        return $this->redirectRoute('attendance.index', navigate: true);
    }

    public function render(AttendanceService $service)
    {
        $students = $this->session->studyGroup->activeStudents()->orderBy('students.name')->get()->keyBy('id');
        $counts = collect($this->statuses)->countBy();

        return view('livewire.attendance.mark', [
            'students' => $students,
            'counts' => $counts,
            'canEdit' => $service->canEdit($this->session, auth()->user()),
            'levelStatuses' => AttendanceStatus::forLevel(auth()->user()->tenant->level),
        ]);
    }
}
