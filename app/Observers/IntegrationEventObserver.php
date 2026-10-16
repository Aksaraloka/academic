<?php

namespace App\Observers;

use App\Models\AcademicTerm;
use App\Models\Faculty;
use App\Models\FeeRate;
use App\Models\Program;
use App\Models\Student;
use App\Models\StudyGroup;
use App\Models\StudyGroupMember;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\IntegrationEvents;
use Illuminate\Database\Eloquent\Model;

/**
 * Menerbitkan webhook ke LMS saat data yang diperlukan LMS berubah. Satu pengamat untuk semua model terkait agar
 * pemetaan "perubahan -> event" ada di satu tempat dan berlaku untuk setiap jalur perubahan (layanan, impor, form).
 * Perubahan lewat query massal (tanpa model) tidak terlihat di sini; jalur itu harus memakai update per model.
 *
 * Pemetaan: kelas -> `class.updated`/`class.deleted`; kunci nilai -> `grades.locked`; semester -> `term.updated`;
 * keanggotaan kelas -> `roster.updated`; akun, NIM/NIDN, peran dosen wali -> `user.updated`; status -> `student.status_changed`.
 */
class IntegrationEventObserver
{
    private const ASSIGNMENT_FIELDS = ['academic_term_id', 'teacher_id', 'subject_id', 'study_group_id', 'section', 'capacity'];

    private const TERM_FIELDS = ['name', 'semester', 'starts_at', 'ends_at', 'is_active', 'is_locked', 'grading_closes_on'];

    public function __construct(private readonly IntegrationEvents $events) {}

    public function created(Model $model): void
    {
        if (! $this->events->anyEnabled()) {
            return;
        }

        match (true) {
            $model instanceof TeachingAssignment => $this->classChanged($model),
            $model instanceof AcademicTerm => $this->term($model),
            $model instanceof StudyGroupMember => $this->groupRoster($model),
            $model instanceof Program => $this->program($model->id, $model->tenant_id),
            $model instanceof FeeRate => $this->feeRates($model->program_id, $model->tenant_id),
            default => null,
        };
    }

    public function updated(Model $model): void
    {
        if (! $this->events->anyEnabled()) {
            return;
        }

        match (true) {
            $model instanceof TeachingAssignment => $this->assignmentUpdated($model),
            $model instanceof AcademicTerm => $model->wasChanged(self::TERM_FIELDS) ? $this->term($model) : null,
            $model instanceof StudyGroupMember => $this->groupRoster($model),
            $model instanceof Subject => $model->wasChanged(['name', 'code', 'credits']) ? $this->classesWhere('subject_id', $model->id, $model->tenant_id) : null,
            $model instanceof StudyGroup => $this->groupUpdated($model),
            $model instanceof Student => $this->studentUpdated($model),
            $model instanceof Program => $model->wasChanged(['name', 'code', 'degree', 'faculty_id', 'accreditation']) ? $this->program($model->id, $model->tenant_id) : null,
            $model instanceof Tenant => $this->tenantUpdated($model),
            $model instanceof Faculty => $model->wasChanged('name') ? $this->facultyRenamed($model) : null,
            $model instanceof FeeRate => $this->feeRateUpdated($model),
            $model instanceof Teacher => $model->wasChanged(['name', 'nip', 'nidn', 'user_id']) ? $this->user($model->user_id, $model->tenant_id) : null,
            $model instanceof User => $model->wasChanged(['name', 'email', 'role']) ? $this->user($model->id, $model->tenant_id) : null,
            default => null,
        };
    }

    public function deleted(Model $model): void
    {
        if (! $this->events->anyEnabled()) {
            return;
        }

        match (true) {
            $model instanceof TeachingAssignment => $this->events->publish('class.deleted', ['class_id' => $model->id], $model->tenant_id),
            $model instanceof StudyGroupMember => $this->groupRoster($model),
            $model instanceof Program => $this->program($model->id, $model->tenant_id),
            $model instanceof FeeRate => $this->feeRates($model->program_id, $model->tenant_id),
            default => null,
        };
    }

    private function program(int $programId, int $tenantId): void
    {
        $this->events->publish('program.updated', ['program_id' => $programId], $tenantId);
    }

    /**
     * Model biaya kampus berubah (UKT bertingkat <-> flat): tarif semua prodi dibaca ulang oleh Admission.
     */
    private function tenantUpdated(Tenant $tenant): void
    {
        if (! $tenant->wasChanged('settings')) {
            return;
        }

        $previous = ((array) $tenant->getOriginal('settings'))['fee_model'] ?? null;

        if (($previous === 'flat' ? 'flat' : 'ukt') === $tenant->feeModel()) {
            return;
        }

        Program::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->pluck('id')
            ->each(fn (int $programId) => $this->feeRates($programId, $tenant->id));
    }

    private function feeRates(int $programId, int $tenantId): void
    {
        $this->events->publish('fee_rates.updated', ['program_id' => $programId], $tenantId);
    }

    private function feeRateUpdated(FeeRate $rate): void
    {
        if (! $rate->wasChanged(['program_id', 'kind', 'ukt_group', 'amount'])) {
            return;
        }

        $this->feeRates($rate->program_id, $rate->tenant_id);

        if ($rate->wasChanged('program_id')) {
            $this->feeRates((int) $rate->getOriginal('program_id'), $rate->tenant_id);
        }
    }

    private function facultyRenamed(Faculty $faculty): void
    {
        Program::query()->withoutGlobalScopes()->where('faculty_id', $faculty->id)->pluck('id')
            ->each(fn (int $programId) => $this->program($programId, $faculty->tenant_id));
    }

    private function assignmentUpdated(TeachingAssignment $assignment): void
    {
        if ($assignment->wasChanged(self::ASSIGNMENT_FIELDS)) {
            $this->classChanged($assignment);

            if ($assignment->wasChanged('study_group_id')) {
                $this->events->publish('roster.updated', ['class_id' => $assignment->id], $assignment->tenant_id);
            }
        }

        if ($assignment->wasChanged('grades_locked_at') && $assignment->grades_locked_at !== null) {
            $this->events->publish('grades.locked', ['class_id' => $assignment->id], $assignment->tenant_id);
        }
    }

    private function classChanged(TeachingAssignment $assignment): void
    {
        $this->events->publish('class.updated', ['class_id' => $assignment->id], $assignment->tenant_id);
    }

    private function term(AcademicTerm $term): void
    {
        $this->events->publish('term.updated', ['term_id' => $term->id], $term->tenant_id);
    }

    /**
     * Keanggotaan kelas angkatan berubah: peserta semua kuliah kelas itu ikut berubah.
     */
    private function groupRoster(StudyGroupMember $member): void
    {
        $group = StudyGroup::query()->withoutGlobalScopes()->find($member->study_group_id);

        if ($group === null) {
            return;
        }

        TeachingAssignment::query()->withoutGlobalScopes()->where('study_group_id', $group->id)->pluck('id')
            ->each(fn (int $classId) => $this->events->publish('roster.updated', ['class_id' => $classId], $group->tenant_id));
    }

    private function groupUpdated(StudyGroup $group): void
    {
        if ($group->wasChanged('name')) {
            $this->classesWhere('study_group_id', $group->id, $group->tenant_id);
        }

        // Dosen wali memengaruhi `is_homeroom` pada profil SSO.
        if ($group->wasChanged('homeroom_teacher_id')) {
            foreach (array_filter([$group->getOriginal('homeroom_teacher_id'), $group->homeroom_teacher_id]) as $teacherId) {
                $this->user(Teacher::query()->withoutGlobalScopes()->whereKey($teacherId)->value('user_id'), $group->tenant_id);
            }
        }
    }

    private function studentUpdated(Student $student): void
    {
        if ($student->user_id !== null && $student->wasChanged('status')) {
            $this->events->publish('student.status_changed', ['user_id' => $student->user_id, 'status' => $student->status->value], $student->tenant_id);
        }

        if ($student->wasChanged(['nim', 'user_id'])) {
            $this->user($student->user_id, $student->tenant_id);
        }
    }

    private function classesWhere(string $column, int $value, int $tenantId): void
    {
        TeachingAssignment::query()->withoutGlobalScopes()->where($column, $value)->pluck('id')
            ->each(fn (int $classId) => $this->events->publish('class.updated', ['class_id' => $classId], $tenantId));
    }

    private function user(?int $userId, ?int $tenantId): void
    {
        if ($userId !== null) {
            $this->events->publish('user.updated', ['user_id' => $userId], $tenantId);
        }
    }
}
