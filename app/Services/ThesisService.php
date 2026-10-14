<?php

namespace App\Services;

use App\Enums\DefenseResult;
use App\Enums\GuidanceStatus;
use App\Enums\Role;
use App\Enums\StudentStatus;
use App\Enums\ThesisStatus;
use App\Models\AcademicTerm;
use App\Models\Student;
use App\Models\Thesis;
use App\Models\ThesisDefense;
use App\Models\ThesisGuidanceLog;
use App\Models\User;
use App\Support\GradeScale;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Tugas akhir: pengajuan judul, penetapan pembimbing, log bimbingan, persetujuan maju sidang, jadwal sidang, dan hasilnya.
 */
class ThesisService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly AcademicRecord $records,
    ) {}

    public function isManager(User $user): bool
    {
        return $user->hasRole(Role::Admin, Role::Principal, Role::Curriculum);
    }

    public function isSupervisor(User $user, Thesis $thesis): bool
    {
        return $user->hasRole(Role::Teacher) && $user->teacher !== null
            && $thesis->supervisors->contains('teacher_id', $user->teacher->id);
    }

    public function isPrimarySupervisor(User $user, Thesis $thesis): bool
    {
        return $user->hasRole(Role::Teacher) && $user->teacher !== null
            && $thesis->supervisors->where('role', 'primary')->contains('teacher_id', $user->teacher->id);
    }

    /**
     * Tugas akhir terbaru mahasiswa yang masih berjalan atau sudah lulus sidang.
     */
    public function current(Student $student): ?Thesis
    {
        return Thesis::query()->with(['supervisors.teacher', 'latestDefense'])
            ->where('student_id', $student->id)->where('status', '!=', ThesisStatus::Rejected)->latest('id')->first();
    }

    public function propose(Student $student, string $title, ?string $abstract): Thesis
    {
        if ($student->status !== StudentStatus::Active) {
            throw ValidationException::withMessages(['title' => 'Hanya mahasiswa berstatus aktif yang bisa mengajukan judul.']);
        }

        if ($this->current($student)) {
            throw ValidationException::withMessages(['title' => 'Kamu masih punya tugas akhir yang berjalan atau sudah selesai.']);
        }

        $min = (int) $student->tenant->setting('thesis_min_credits_propose');
        $credits = $this->records->eligibility($student)['credits'];

        if ($credits < $min) {
            throw ValidationException::withMessages(['title' => "SKS lulusmu {$credits}; pengajuan judul butuh minimal {$min} SKS."]);
        }

        $thesis = Thesis::create([
            'tenant_id' => $student->tenant_id, 'student_id' => $student->id, 'academic_term_id' => AcademicTerm::current()?->id,
            'title' => trim($title), 'abstract' => filled($abstract) ? trim((string) $abstract) : null, 'status' => ThesisStatus::Proposed,
        ]);
        $this->audit->log('thesis.proposed', $thesis, [], ['title' => $thesis->title]);

        return $thesis;
    }

    /**
     * Setujui judul sekaligus menetapkan pembimbing utama dan (opsional) pendamping.
     */
    public function approve(Thesis $thesis, User $actor, int $primaryId, ?int $secondaryId, ?string $note): void
    {
        abort_unless($this->isManager($actor), 403);

        if ($thesis->status !== ThesisStatus::Proposed) {
            throw ValidationException::withMessages(['decision' => 'Judul ini tidak sedang menunggu keputusan.']);
        }

        $this->syncSupervisors($thesis, $primaryId, $secondaryId);

        $thesis->update(['status' => ThesisStatus::Supervised, 'decision_note' => filled($note) ? trim((string) $note) : null, 'approved_by' => $actor->id, 'approved_at' => now()]);
        $this->audit->log('thesis.approved', $thesis, [], ['primary' => $primaryId, 'secondary' => $secondaryId]);
    }

    public function reject(Thesis $thesis, User $actor, string $note): void
    {
        abort_unless($this->isManager($actor), 403);

        if ($thesis->status !== ThesisStatus::Proposed) {
            throw ValidationException::withMessages(['decision' => 'Judul ini tidak sedang menunggu keputusan.']);
        }

        if (blank($note)) {
            throw ValidationException::withMessages(['decision' => 'Alasan penolakan wajib diisi.']);
        }

        $thesis->update(['status' => ThesisStatus::Rejected, 'decision_note' => trim($note), 'approved_by' => $actor->id, 'approved_at' => now()]);
        $this->audit->log('thesis.rejected', $thesis, [], ['note' => $note]);
    }

    public function assignSupervisors(Thesis $thesis, User $actor, int $primaryId, ?int $secondaryId): void
    {
        abort_unless($this->isManager($actor), 403);

        if (! in_array($thesis->status, [ThesisStatus::Supervised, ThesisStatus::DefenseRequested], true)) {
            throw ValidationException::withMessages(['primaryId' => 'Pembimbing hanya bisa diubah saat tugas akhir dalam bimbingan.']);
        }

        $this->syncSupervisors($thesis, $primaryId, $secondaryId);
        $this->audit->log('thesis.supervisors_changed', $thesis, [], ['primary' => $primaryId, 'secondary' => $secondaryId]);
    }

    private function syncSupervisors(Thesis $thesis, int $primaryId, ?int $secondaryId): void
    {
        if ($secondaryId !== null && $secondaryId === $primaryId) {
            throw ValidationException::withMessages(['secondaryId' => 'Pembimbing pendamping harus berbeda dari pembimbing utama.']);
        }

        DB::transaction(function () use ($thesis, $primaryId, $secondaryId): void {
            $thesis->supervisors()->delete();
            $thesis->supervisors()->create(['teacher_id' => $primaryId, 'role' => 'primary']);

            if ($secondaryId !== null) {
                $thesis->supervisors()->create(['teacher_id' => $secondaryId, 'role' => 'secondary']);
            }
        });

        $thesis->unsetRelation('supervisors');
    }

    public function addLog(Thesis $thesis, int $teacherId, CarbonInterface $heldOn, string $topic, string $summary, ?string $nextSteps): ThesisGuidanceLog
    {
        if ($thesis->status !== ThesisStatus::Supervised) {
            throw ValidationException::withMessages(['topic' => 'Log bimbingan hanya bisa ditambahkan saat tugas akhir dalam bimbingan.']);
        }

        if (! $thesis->supervisors()->where('teacher_id', $teacherId)->exists()) {
            throw ValidationException::withMessages(['teacherId' => 'Pilih salah satu pembimbingmu.']);
        }

        return ThesisGuidanceLog::create([
            'thesis_id' => $thesis->id, 'teacher_id' => $teacherId, 'held_on' => $heldOn, 'topic' => trim($topic),
            'summary' => trim($summary), 'next_steps' => filled($nextSteps) ? trim((string) $nextSteps) : null, 'status' => GuidanceStatus::Pending,
        ]);
    }

    public function verifyLog(ThesisGuidanceLog $log, User $actor, bool $approve, ?string $feedback): void
    {
        $thesis = $log->thesis->loadMissing('supervisors');

        if ($log->teacher_id !== $actor->teacher?->id || ! $this->isSupervisor($actor, $thesis)) {
            abort(403);
        }

        if (! $approve && blank($feedback)) {
            throw ValidationException::withMessages(['feedback' => 'Catatan perbaikan wajib diisi.']);
        }

        $log->update([
            'status' => $approve ? GuidanceStatus::Verified : GuidanceStatus::Revise,
            'feedback' => filled($feedback) ? trim((string) $feedback) : null, 'verified_at' => now(),
        ]);
    }

    /**
     * Prasyarat maju sidang: log bimbingan terverifikasi, SKS minimal, dan persetujuan pembimbing utama.
     *
     * @return array{verified: int, min_logs: int, credits: int, min_credits: int, consent: bool, logs_ok: bool, credits_ok: bool, ready: bool}
     */
    public function readiness(Thesis $thesis): array
    {
        $tenant = $thesis->student->tenant;
        $verified = $thesis->logs()->where('status', GuidanceStatus::Verified)->count();
        $minLogs = (int) $tenant->setting('thesis_min_guidance');
        $credits = $this->records->eligibility($thesis->student)['credits'];
        $minCredits = (int) $tenant->setting('thesis_min_credits_defense');

        return [
            'verified' => $verified, 'min_logs' => $minLogs, 'credits' => $credits, 'min_credits' => $minCredits,
            'consent' => $thesis->defense_consent_at !== null,
            'logs_ok' => $verified >= $minLogs, 'credits_ok' => $credits >= $minCredits,
            'ready' => $verified >= $minLogs && $credits >= $minCredits && $thesis->defense_consent_at !== null,
        ];
    }

    /**
     * Pembimbing utama menyetujui mahasiswa maju sidang setelah log bimbingan cukup.
     */
    public function grantConsent(Thesis $thesis, User $actor): void
    {
        $thesis->loadMissing('supervisors', 'student.tenant');
        abort_unless($this->isPrimarySupervisor($actor, $thesis), 403);

        if ($thesis->status !== ThesisStatus::Supervised) {
            throw ValidationException::withMessages(['consent' => 'Persetujuan hanya untuk tugas akhir yang sedang dibimbing.']);
        }

        $state = $this->readiness($thesis);

        if (! $state['logs_ok']) {
            throw ValidationException::withMessages(['consent' => "Log bimbingan terverifikasi baru {$state['verified']} dari minimal {$state['min_logs']}."]);
        }

        $thesis->update(['defense_consent_at' => now()]);
        $this->audit->log('thesis.defense_consent', $thesis);
    }

    public function requestDefense(Thesis $thesis, Student $student): void
    {
        abort_unless($thesis->student_id === $student->id, 403);

        if ($thesis->status !== ThesisStatus::Supervised) {
            throw ValidationException::withMessages(['defense' => 'Sidang hanya bisa diajukan saat tugas akhir dalam bimbingan.']);
        }

        $state = $this->readiness($thesis->loadMissing('student.tenant'));

        $problem = match (true) {
            ! $state['logs_ok'] => "Log bimbingan terverifikasi baru {$state['verified']} dari minimal {$state['min_logs']}.",
            ! $state['credits_ok'] => "SKS lulusmu {$state['credits']}, minimal {$state['min_credits']} untuk maju sidang.",
            ! $state['consent'] => 'Pembimbing utama belum menyetujui kamu maju sidang.',
            default => null,
        };

        if ($problem) {
            throw ValidationException::withMessages(['defense' => $problem]);
        }

        $thesis->update(['status' => ThesisStatus::DefenseRequested]);
        $this->audit->log('thesis.defense_requested', $thesis);
    }

    public function scheduleDefense(Thesis $thesis, User $actor, CarbonInterface $date, string $startsAt, string $endsAt, ?int $roomId, int $chairId, int $examinerId): ThesisDefense
    {
        abort_unless($this->isManager($actor), 403);
        $thesis->loadMissing('supervisors');

        if (! in_array($thesis->status, [ThesisStatus::DefenseRequested, ThesisStatus::DefenseScheduled], true)) {
            throw ValidationException::withMessages(['date' => 'Sidang dijadwalkan setelah mahasiswa mengajukannya.']);
        }

        if ($chairId === $examinerId) {
            throw ValidationException::withMessages(['examinerId' => 'Ketua dan penguji harus berbeda.']);
        }

        if ($thesis->supervisors->where('role', 'primary')->contains('teacher_id', $examinerId) || $thesis->supervisors->where('role', 'primary')->contains('teacher_id', $chairId)) {
            throw ValidationException::withMessages(['chairId' => 'Pembimbing utama tidak boleh menjadi ketua atau penguji sidangnya sendiri.']);
        }

        $clash = ThesisDefense::query()->where('status', 'scheduled')->whereDate('scheduled_on', $date->toDateString())
            ->when($thesis->latestDefense, fn ($query, $own) => $query->whereKeyNot($own->id))
            ->get()
            ->filter(fn (ThesisDefense $other): bool => $other->starts_at < $endsAt && $startsAt < $other->ends_at)
            ->first(fn (ThesisDefense $other): bool => in_array($other->chair_id, [$chairId, $examinerId], true) || in_array($other->examiner_id, [$chairId, $examinerId], true) || ($roomId !== null && $other->room_id === $roomId));

        if ($clash) {
            throw ValidationException::withMessages(['date' => 'Penguji atau ruang bentrok dengan sidang lain pada jam ini.']);
        }

        $attributes = [
            'scheduled_on' => $date, 'starts_at' => $startsAt, 'ends_at' => $endsAt, 'room_id' => $roomId,
            'chair_id' => $chairId, 'examiner_id' => $examinerId, 'status' => 'scheduled', 'scheduled_by' => $actor->id,
        ];

        $existing = $thesis->defenses()->where('status', 'scheduled')->first();

        $defense = DB::transaction(function () use ($thesis, $existing, $attributes): ThesisDefense {
            $thesis->update(['status' => ThesisStatus::DefenseScheduled]);

            if ($existing) {
                $existing->update($attributes);

                return $existing;
            }

            return $thesis->defenses()->create($attributes);
        });

        $this->audit->log('thesis.defense_scheduled', $thesis, [], ['date' => $date->toDateString()]);

        return $defense;
    }

    /**
     * Catat hasil sidang. Lulus (termasuk dengan revisi) menyelesaikan tugas akhir; tidak lulus mengembalikannya ke bimbingan.
     */
    public function recordResult(ThesisDefense $defense, User $actor, DefenseResult $result, float $score, ?string $notes, ?CarbonInterface $revisionDeadline): void
    {
        $thesis = $defense->thesis->loadMissing('supervisors', 'student.tenant');

        abort_unless($this->isManager($actor) || $this->isPrimarySupervisor($actor, $thesis), 403);

        if ($defense->status !== 'scheduled' || $thesis->status !== ThesisStatus::DefenseScheduled) {
            throw ValidationException::withMessages(['result' => 'Sidang ini tidak sedang menunggu hasil.']);
        }

        DB::transaction(function () use ($defense, $thesis, $result, $score, $notes, $revisionDeadline): void {
            $defense->update([
                'status' => 'done', 'result' => $result, 'score' => $score, 'notes' => filled($notes) ? trim((string) $notes) : null,
                'revision_deadline' => $result === DefenseResult::PassedRevision ? $revisionDeadline : null,
            ]);

            if ($result->isPass()) {
                $thesis->update([
                    'status' => ThesisStatus::Completed, 'completed_at' => now(), 'final_score' => $score,
                    'final_letter' => GradeScale::for($thesis->student->tenant)->letterFor($score),
                ]);
            } else {
                $thesis->update(['status' => ThesisStatus::Supervised, 'defense_consent_at' => null]);
            }
        });

        $this->audit->log('thesis.defense_result', $thesis, [], ['result' => $result->value, 'score' => $score]);
    }
}
