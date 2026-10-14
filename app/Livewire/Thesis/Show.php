<?php

namespace App\Livewire\Thesis;

use App\Enums\DefenseResult;
use App\Enums\ThesisStatus;
use App\Models\Room;
use App\Models\Teacher;
use App\Models\Thesis;
use App\Models\ThesisDefense;
use App\Models\ThesisGuidanceLog;
use App\Services\ThesisService;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Detail tugas akhir')]
class Show extends Component
{
    #[Locked]
    public int $thesisId;

    public string $primaryId = '';

    public string $secondaryId = '';

    public string $note = '';

    public string $date = '';

    public string $starts_at = '09:00';

    public string $ends_at = '10:30';

    public string $roomId = '';

    public string $chairId = '';

    public string $examinerId = '';

    public string $result = 'passed';

    public string $score = '';

    public string $resultNotes = '';

    public string $revisionDeadline = '';

    public ?int $revisingLogId = null;

    public string $feedback = '';

    public function mount(Thesis $thesis, ThesisService $service): void
    {
        $thesis->load('supervisors', 'defenses');
        $user = auth()->user();
        $examiner = $user->teacher && $thesis->defenses->contains(fn (ThesisDefense $defense): bool => in_array($user->teacher->id, [$defense->chair_id, $defense->examiner_id], true));

        abort_unless($service->isManager($user) || $service->isSupervisor($user, $thesis) || $examiner, 403);

        $this->thesisId = $thesis->id;
        $this->primaryId = (string) $thesis->supervisors->firstWhere('role', 'primary')?->teacher_id;
        $this->secondaryId = (string) $thesis->supervisors->firstWhere('role', 'secondary')?->teacher_id;

        $defense = $thesis->defenses->firstWhere('status', 'scheduled');

        if ($defense) {
            $this->date = $defense->scheduled_on->toDateString();
            $this->starts_at = substr($defense->starts_at, 0, 5);
            $this->ends_at = substr($defense->ends_at, 0, 5);
            $this->roomId = (string) $defense->room_id;
            $this->chairId = (string) $defense->chair_id;
            $this->examinerId = (string) $defense->examiner_id;
        }
    }

    private function thesis(): Thesis
    {
        return Thesis::query()->with(['student.program', 'supervisors.teacher', 'logs.teacher', 'defenses.room', 'defenses.chair', 'defenses.examiner'])->findOrFail($this->thesisId);
    }

    public function approve(ThesisService $service): void
    {
        $this->resetErrorBag();

        $this->validate(['primaryId' => ['required', 'exists:teachers,id'], 'secondaryId' => ['nullable', 'exists:teachers,id'], 'note' => ['nullable', 'string', 'max:500']], [], ['primaryId' => 'pembimbing utama', 'secondaryId' => 'pembimbing pendamping']);

        $service->approve($this->thesis(), auth()->user(), (int) $this->primaryId, $this->secondaryId !== '' ? (int) $this->secondaryId : null, $this->note);

        $this->dispatch('toast', message: 'Judul disetujui dan pembimbing ditetapkan.', type: 'success');
    }

    public function reject(ThesisService $service): void
    {
        $this->resetErrorBag();

        $this->validate(['note' => ['required', 'string', 'min:5', 'max:500']], [], ['note' => 'alasan penolakan']);

        $service->reject($this->thesis(), auth()->user(), $this->note);

        $this->dispatch('toast', message: 'Judul ditolak.', type: 'success');
    }

    public function changeSupervisors(ThesisService $service): void
    {
        $this->resetErrorBag();

        $this->validate(['primaryId' => ['required', 'exists:teachers,id'], 'secondaryId' => ['nullable', 'exists:teachers,id']], [], ['primaryId' => 'pembimbing utama']);

        $service->assignSupervisors($this->thesis(), auth()->user(), (int) $this->primaryId, $this->secondaryId !== '' ? (int) $this->secondaryId : null);

        $this->dispatch('toast', message: 'Pembimbing diperbarui.', type: 'success');
    }

    public function verifyLog(int $logId, ThesisService $service): void
    {
        $this->reset(['revisingLogId', 'feedback']);
        $this->resetErrorBag();

        $service->verifyLog(ThesisGuidanceLog::query()->where('thesis_id', $this->thesisId)->findOrFail($logId), auth()->user(), true, null);

        $this->dispatch('toast', message: 'Log bimbingan diverifikasi.', type: 'success');
    }

    public function startRevise(int $logId): void
    {
        $this->revisingLogId = $logId;
        $this->feedback = '';
        $this->resetErrorBag();
    }

    public function reviseLog(ThesisService $service): void
    {
        $this->validate(['feedback' => ['required', 'string', 'min:5', 'max:500']], [], ['feedback' => 'catatan perbaikan']);

        $service->verifyLog(ThesisGuidanceLog::query()->where('thesis_id', $this->thesisId)->findOrFail($this->revisingLogId), auth()->user(), false, $this->feedback);

        $this->reset(['revisingLogId', 'feedback']);
        $this->dispatch('toast', message: 'Log dikembalikan untuk diperbaiki.', type: 'success');
    }

    public function grantConsent(ThesisService $service): void
    {
        $this->resetErrorBag();

        $service->grantConsent($this->thesis(), auth()->user());

        $this->dispatch('toast', message: 'Persetujuan maju sidang diberikan.', type: 'success');
    }

    public function scheduleDefense(ThesisService $service): void
    {
        $this->resetErrorBag();

        $this->validate([
            'date' => ['required', 'date', 'after_or_equal:today'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
            'roomId' => ['nullable', 'exists:rooms,id'],
            'chairId' => ['required', 'exists:teachers,id'],
            'examinerId' => ['required', 'exists:teachers,id'],
        ], [], ['date' => 'tanggal sidang', 'starts_at' => 'jam mulai', 'ends_at' => 'jam selesai', 'roomId' => 'ruang', 'chairId' => 'ketua sidang', 'examinerId' => 'penguji']);

        $service->scheduleDefense($this->thesis(), auth()->user(), now()->parse($this->date), $this->starts_at, $this->ends_at, $this->roomId !== '' ? (int) $this->roomId : null, (int) $this->chairId, (int) $this->examinerId);

        $this->dispatch('toast', message: 'Sidang dijadwalkan.', type: 'success');
    }

    public function recordResult(ThesisService $service): void
    {
        $this->resetErrorBag();

        $this->validate([
            'result' => ['required', 'in:passed,passed_revision,failed'],
            'score' => ['required', 'numeric', 'between:0,100'],
            'resultNotes' => ['nullable', 'string', 'max:1000'],
            'revisionDeadline' => ['nullable', 'date'],
        ], [], ['result' => 'hasil', 'score' => 'nilai', 'resultNotes' => 'catatan', 'revisionDeadline' => 'batas revisi']);

        $defense = $this->thesis()->defenses->firstWhere('status', 'scheduled');
        abort_if($defense === null, 404);

        $service->recordResult($defense, auth()->user(), DefenseResult::from($this->result), (float) $this->score, $this->resultNotes, $this->revisionDeadline !== '' ? now()->parse($this->revisionDeadline) : null);

        $this->dispatch('toast', message: 'Hasil sidang dicatat.', type: 'success');
    }

    public function render(ThesisService $service)
    {
        $thesis = $this->thesis();
        $user = auth()->user();
        $manager = $service->isManager($user);
        $primary = $service->isPrimarySupervisor($user, $thesis);

        return view('livewire.thesis.show', [
            'thesis' => $thesis,
            'manager' => $manager,
            'isSupervisor' => $service->isSupervisor($user, $thesis),
            'primary' => $primary,
            'canRecord' => ($manager || $primary) && $thesis->status === ThesisStatus::DefenseScheduled,
            'readiness' => $thesis->status === ThesisStatus::Supervised ? $service->readiness($thesis) : null,
            'teachers' => Teacher::query()->orderBy('name')->get(),
            'rooms' => Room::query()->orderBy('name')->get(),
            'results' => DefenseResult::cases(),
            'myTeacherId' => $user->teacher?->id,
            'scheduledDefense' => $thesis->defenses->firstWhere('status', 'scheduled'),
        ]);
    }
}
