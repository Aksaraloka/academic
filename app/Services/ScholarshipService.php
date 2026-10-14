<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Enums\Role;
use App\Enums\StudentStatus;
use App\Models\Scholarship;
use App\Models\ScholarshipApplication;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Beasiswa: pembukaan, syarat pendaftar, seleksi staf, dan kuota penerima.
 */
class ScholarshipService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly AcademicRecord $records,
    ) {}

    public function isManager(User $user): bool
    {
        return $user->hasRole(Role::Admin, Role::Principal, Role::Curriculum);
    }

    /**
     * Alasan mahasiswa belum boleh mendaftar; larik kosong berarti memenuhi syarat.
     *
     * @return array<int, string>
     */
    public function blockers(Student $student, Scholarship $scholarship): array
    {
        $blockers = [];
        $stats = $this->records->eligibility($student);

        if (! $scholarship->isOpen()) {
            $blockers[] = 'Pendaftaran belum dibuka atau sudah ditutup.';
        }

        if ($student->status !== StudentStatus::Active) {
            $blockers[] = 'Hanya mahasiswa berstatus aktif yang bisa mendaftar.';
        }

        if ($scholarship->min_gpa !== null && ($stats['gpa'] ?? 0) < (float) $scholarship->min_gpa) {
            $blockers[] = 'IPK minimal '.number_format((float) $scholarship->min_gpa, 2, ',', '.').' (IPK kamu '.($stats['gpa'] === null ? 'belum ada' : number_format($stats['gpa'], 2, ',', '.')).').';
        }

        if ($scholarship->min_semester !== null && $this->records->semesterOf($student) < $scholarship->min_semester) {
            $blockers[] = 'Minimal semester '.$scholarship->min_semester.'.';
        }

        if ($scholarship->quota !== null && $scholarship->acceptedCount() >= $scholarship->quota) {
            $blockers[] = 'Kuota penerima sudah penuh.';
        }

        return $blockers;
    }

    public function apply(Student $student, Scholarship $scholarship, string $motivation): ScholarshipApplication
    {
        $existing = ScholarshipApplication::query()->where('scholarship_id', $scholarship->id)->where('student_id', $student->id)->first();

        if ($existing && $existing->status !== ApplicationStatus::Withdrawn) {
            throw ValidationException::withMessages(['motivation' => 'Kamu sudah mendaftar beasiswa ini.']);
        }

        if ($blockers = $this->blockers($student, $scholarship)) {
            throw ValidationException::withMessages(['motivation' => $blockers[0]]);
        }

        $stats = $this->records->eligibility($student);
        $values = [
            'tenant_id' => $student->tenant_id, 'motivation' => trim($motivation), 'gpa' => $stats['gpa'], 'credits' => $stats['credits'],
            'status' => ApplicationStatus::Submitted, 'review_note' => null, 'reviewed_by' => null, 'reviewed_at' => null,
        ];

        $application = $existing
            ? tap($existing)->update($values)
            : ScholarshipApplication::create($values + ['scholarship_id' => $scholarship->id, 'student_id' => $student->id]);
        $this->audit->log('scholarship.applied', $application, [], ['scholarship' => $scholarship->name]);

        return $application;
    }

    public function withdraw(ScholarshipApplication $application, User $actor): void
    {
        abort_unless($actor->student !== null && $actor->student->id === $application->student_id, 403);

        if ($application->status !== ApplicationStatus::Submitted) {
            throw ValidationException::withMessages(['application' => 'Hanya pendaftaran yang belum diputuskan yang bisa dibatalkan.']);
        }

        $application->update(['status' => ApplicationStatus::Withdrawn]);
        $this->audit->log('scholarship.withdrawn', $application);
    }

    public function accept(ScholarshipApplication $application, User $actor, ?string $note): void
    {
        abort_unless($this->isManager($actor), 403);

        DB::transaction(function () use ($application, $actor, $note): void {
            $scholarship = Scholarship::query()->lockForUpdate()->findOrFail($application->scholarship_id);
            $this->ensureSubmitted($application);

            if ($scholarship->quota !== null && $scholarship->acceptedCount() >= $scholarship->quota) {
                throw ValidationException::withMessages(['decision' => 'Kuota penerima sudah penuh.']);
            }

            $application->update(['status' => ApplicationStatus::Accepted, 'review_note' => filled($note) ? trim($note) : null, 'reviewed_by' => $actor->id, 'reviewed_at' => now()]);
        });

        $this->audit->log('scholarship.accepted', $application);
    }

    public function reject(ScholarshipApplication $application, User $actor, string $note): void
    {
        abort_unless($this->isManager($actor), 403);
        $this->ensureSubmitted($application);

        if (blank($note)) {
            throw ValidationException::withMessages(['decision' => 'Alasan wajib diisi.']);
        }

        $application->update(['status' => ApplicationStatus::Rejected, 'review_note' => trim($note), 'reviewed_by' => $actor->id, 'reviewed_at' => now()]);
        $this->audit->log('scholarship.rejected', $application, [], ['note' => $note]);
    }

    private function ensureSubmitted(ScholarshipApplication $application): void
    {
        if ($application->fresh()->status !== ApplicationStatus::Submitted) {
            throw ValidationException::withMessages(['decision' => 'Pendaftaran ini tidak sedang menunggu seleksi.']);
        }
    }
}
