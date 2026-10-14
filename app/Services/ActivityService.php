<?php

namespace App\Services;

use App\Enums\ActivityCategory;
use App\Enums\ActivityLevel;
use App\Enums\ActivityStatus;
use App\Enums\Role;
use App\Models\Student;
use App\Models\StudentActivity;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Catatan aktivitas kemahasiswaan (organisasi, prestasi, sertifikat) yang diverifikasi staf dan dihimpun menjadi SKPI.
 */
class ActivityService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function isManager(User $user): bool
    {
        return $user->hasRole(Role::Admin, Role::Principal, Role::Curriculum);
    }

    /**
     * Usulan poin SKPI dari kategori dan tingkat kegiatan; staf boleh mengubahnya saat verifikasi.
     */
    public function suggestedPoints(StudentActivity $activity): int
    {
        return $activity->category->basePoints() * $activity->level->multiplier();
    }

    /**
     * @param  array{category: string, title: string, organizer: string, role_or_result: ?string, level: string, started_on: string, ended_on: ?string, description: ?string}  $data
     */
    public function submit(Student $student, array $data, ?UploadedFile $evidence): StudentActivity
    {
        $activity = StudentActivity::create([
            'tenant_id' => $student->tenant_id, 'student_id' => $student->id,
            'category' => ActivityCategory::from($data['category']), 'level' => ActivityLevel::from($data['level']),
            'title' => trim($data['title']), 'organizer' => trim($data['organizer']),
            'role_or_result' => filled($data['role_or_result'] ?? null) ? trim($data['role_or_result']) : null,
            'started_on' => $data['started_on'], 'ended_on' => filled($data['ended_on'] ?? null) ? $data['ended_on'] : null,
            'description' => filled($data['description'] ?? null) ? trim($data['description']) : null,
            'evidence_path' => $evidence?->store("activities/{$student->tenant_id}", 'local'),
            'status' => ActivityStatus::Pending,
        ]);
        $this->audit->log('activity.submitted', $activity, [], ['title' => $activity->title]);

        return $activity;
    }

    public function delete(StudentActivity $activity, User $actor): void
    {
        abort_unless($actor->student !== null && $actor->student->id === $activity->student_id, 403);

        if ($activity->status === ActivityStatus::Verified) {
            throw ValidationException::withMessages(['activity' => 'Kegiatan yang sudah diverifikasi tidak bisa dihapus.']);
        }

        if ($activity->evidence_path) {
            Storage::disk('local')->delete($activity->evidence_path);
        }

        $this->audit->log('activity.deleted', $activity, ['title' => $activity->title]);
        $activity->delete();
    }

    public function verify(StudentActivity $activity, User $actor, int $points, ?string $note): void
    {
        abort_unless($this->isManager($actor), 403);

        if ($activity->status !== ActivityStatus::Pending) {
            throw ValidationException::withMessages(['review' => 'Kegiatan ini tidak sedang menunggu verifikasi.']);
        }

        $activity->update(['status' => ActivityStatus::Verified, 'points' => $points, 'review_note' => filled($note) ? trim($note) : null, 'reviewed_by' => $actor->id, 'reviewed_at' => now()]);
        $this->audit->log('activity.verified', $activity, [], ['points' => $points]);
    }

    public function reject(StudentActivity $activity, User $actor, string $note): void
    {
        abort_unless($this->isManager($actor), 403);

        if ($activity->status !== ActivityStatus::Pending) {
            throw ValidationException::withMessages(['review' => 'Kegiatan ini tidak sedang menunggu verifikasi.']);
        }

        if (blank($note)) {
            throw ValidationException::withMessages(['review' => 'Alasan penolakan wajib diisi.']);
        }

        $activity->update(['status' => ActivityStatus::Rejected, 'points' => null, 'review_note' => trim($note), 'reviewed_by' => $actor->id, 'reviewed_at' => now()]);
        $this->audit->log('activity.rejected', $activity, [], ['note' => $note]);
    }

    /**
     * Kegiatan terverifikasi yang dikelompokkan per kategori untuk SKPI, lengkap dengan total poin.
     *
     * @return array{groups: Collection<string, Collection<int, StudentActivity>>, total_points: int, count: int}
     */
    public function skpi(Student $student): array
    {
        $activities = StudentActivity::query()->where('student_id', $student->id)->where('status', ActivityStatus::Verified)
            ->orderBy('started_on')->get();

        return [
            'groups' => collect(ActivityCategory::cases())
                ->mapWithKeys(fn (ActivityCategory $category): array => [$category->value => $activities->where('category', $category)->values()])
                ->filter(fn (Collection $items): bool => $items->isNotEmpty()),
            'total_points' => (int) $activities->sum('points'),
            'count' => $activities->count(),
        ];
    }
}
