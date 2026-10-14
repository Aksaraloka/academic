<?php

namespace App\Services;

use App\Enums\InternshipStatus;
use App\Enums\Role;
use App\Enums\StudentStatus;
use App\Models\Internship;
use App\Models\Student;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Magang dan program MBKM: pengajuan, persetujuan prodi dengan dosen pembimbing, dan penilaian akhir.
 */
class InternshipService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly AcademicRecord $records,
    ) {}

    public function isManager(User $user): bool
    {
        return $user->hasRole(Role::Admin, Role::Principal, Role::Curriculum);
    }

    public function isSupervisor(User $user, Internship $internship): bool
    {
        return $user->hasRole(Role::Teacher) && $user->teacher !== null && $internship->supervisor_id === $user->teacher->id;
    }

    /**
     * @param  array{type: string, partner_name: string, position: ?string, location: ?string, field_supervisor: ?string, starts_on: string, ends_on: string, description: ?string, proposed_credits: int|string}  $data
     */
    public function submit(Student $student, array $data): Internship
    {
        if ($student->status !== StudentStatus::Active) {
            throw ValidationException::withMessages(['partner_name' => 'Hanya mahasiswa berstatus aktif yang bisa mengajukan magang atau MBKM.']);
        }

        $min = (int) $student->tenant->setting('internship_min_credits');
        $credits = $this->records->eligibility($student)['credits'];

        if ($credits < $min) {
            throw ValidationException::withMessages(['partner_name' => "SKS lulusmu {$credits}; magang atau MBKM butuh minimal {$min} SKS."]);
        }

        $overlap = Internship::query()->where('student_id', $student->id)
            ->whereIn('status', [InternshipStatus::Submitted, InternshipStatus::Approved])
            ->whereDate('starts_on', '<=', $data['ends_on'])->whereDate('ends_on', '>=', $data['starts_on'])->exists();

        if ($overlap) {
            throw ValidationException::withMessages(['starts_on' => 'Periode ini bertabrakan dengan pengajuan magang atau MBKM lain yang masih berjalan.']);
        }

        $internship = Internship::create([
            'tenant_id' => $student->tenant_id, 'student_id' => $student->id, 'type' => $data['type'],
            'partner_name' => trim($data['partner_name']),
            'position' => filled($data['position'] ?? null) ? trim($data['position']) : null,
            'location' => filled($data['location'] ?? null) ? trim($data['location']) : null,
            'field_supervisor' => filled($data['field_supervisor'] ?? null) ? trim($data['field_supervisor']) : null,
            'starts_on' => $data['starts_on'], 'ends_on' => $data['ends_on'],
            'description' => filled($data['description'] ?? null) ? trim($data['description']) : null,
            'proposed_credits' => (int) $data['proposed_credits'], 'status' => InternshipStatus::Submitted,
        ]);
        $this->audit->log('internship.submitted', $internship, [], ['partner' => $internship->partner_name]);

        return $internship;
    }

    public function approve(Internship $internship, User $actor, int $supervisorId, ?string $note): void
    {
        abort_unless($this->isManager($actor), 403);
        $this->ensureStatus($internship, InternshipStatus::Submitted);

        $internship->update([
            'status' => InternshipStatus::Approved, 'supervisor_id' => $supervisorId,
            'decision_note' => filled($note) ? trim($note) : null, 'decided_by' => $actor->id, 'decided_at' => now(),
        ]);
        $this->audit->log('internship.approved', $internship, [], ['supervisor' => $supervisorId]);
    }

    public function reject(Internship $internship, User $actor, string $note): void
    {
        abort_unless($this->isManager($actor), 403);
        $this->ensureStatus($internship, InternshipStatus::Submitted);

        if (blank($note)) {
            throw ValidationException::withMessages(['decision' => 'Alasan penolakan wajib diisi.']);
        }

        $internship->update(['status' => InternshipStatus::Rejected, 'decision_note' => trim($note), 'decided_by' => $actor->id, 'decided_at' => now()]);
        $this->audit->log('internship.rejected', $internship, [], ['note' => $note]);
    }

    public function cancel(Internship $internship, User $actor): void
    {
        abort_unless($actor->student !== null && $actor->student->id === $internship->student_id, 403);

        if (! in_array($internship->status, [InternshipStatus::Submitted, InternshipStatus::Approved], true) || $internship->starts_on->isPast()) {
            throw ValidationException::withMessages(['internship' => 'Pengajuan ini sudah berjalan atau selesai sehingga tidak bisa dibatalkan.']);
        }

        $internship->update(['status' => InternshipStatus::Cancelled]);
        $this->audit->log('internship.cancelled', $internship);
    }

    /**
     * Tutup program dan catat nilai akhir; dilakukan dosen pembimbing atau staf setelah program dimulai.
     */
    public function complete(Internship $internship, User $actor, string $letter, ?string $note): void
    {
        abort_unless($this->isManager($actor) || $this->isSupervisor($actor, $internship), 403);
        $this->ensureStatus($internship, InternshipStatus::Approved);

        if ($internship->starts_on->isFuture()) {
            throw ValidationException::withMessages(['complete' => 'Program belum dimulai.']);
        }

        $internship->update([
            'status' => InternshipStatus::Completed, 'final_letter' => $letter,
            'evaluation_note' => filled($note) ? trim($note) : null, 'completed_at' => now(),
        ]);
        $this->audit->log('internship.completed', $internship, [], ['letter' => $letter]);
    }

    private function ensureStatus(Internship $internship, InternshipStatus $expected): void
    {
        if ($internship->fresh()->status !== $expected) {
            throw ValidationException::withMessages(['decision' => 'Status pengajuan sudah berubah. Muat ulang halaman ini.']);
        }
    }
}
