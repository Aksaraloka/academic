<?php

namespace App\Services;

use App\Enums\AttendanceMode;
use App\Enums\AttendanceStatus;
use App\Enums\Role;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\LeaveRequest;
use App\Models\Schedule;
use App\Models\StudyGroup;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Buka sesi presensi dari jadwal hari ini; semua mahasiswa aktif diberi status Hadir (P-01, P-02).
     */
    public function openSession(Schedule $schedule, User $opener, AttendanceMode $mode = AttendanceMode::Manual): AttendanceSession
    {
        $group = $schedule->assignment->studyGroup;

        return $this->createSession($group, $opener, $mode, $schedule);
    }

    /**
     * Buka sesi presensi harian oleh dosen wali (P-06).
     */
    public function openDailySession(StudyGroup $group, User $opener): AttendanceSession
    {
        return $this->createSession($group, $opener, AttendanceMode::Daily, null);
    }

    /**
     * @param  array<int, string|AttendanceStatus>  $statuses  student_id => status
     * @param  array<int, string>  $notes  student_id => catatan
     */
    public function saveStatuses(AttendanceSession $session, User $actor, array $statuses, array $notes = []): void
    {
        $this->authorizeEdit($session, $actor);
        $isCorrection = ! $session->date->isToday();

        DB::transaction(function () use ($session, $actor, $statuses, $notes, $isCorrection): void {
            foreach ($statuses as $studentId => $status) {
                $status = $status instanceof AttendanceStatus ? $status : AttendanceStatus::from($status);
                $record = AttendanceRecord::query()
                    ->where('attendance_session_id', $session->id)
                    ->where('student_id', $studentId)
                    ->first();

                if ($record === null) {
                    continue;
                }

                $before = $record->status;
                $record->fill([
                    'status' => $status,
                    'note' => $notes[$studentId] ?? $record->note,
                    'method' => 'manual',
                    'recorded_by' => $actor->id,
                    'recorded_at' => now(),
                ])->save();

                if ($isCorrection && $before !== $status) {
                    $this->audit->log('attendance.corrected', $record, ['status' => $before->value], ['status' => $status->value]);
                }
            }
        });
    }

    public function closeSession(AttendanceSession $session, User $actor): void
    {
        $this->authorizeEdit($session, $actor);
        $session->update(['closed_at' => now()]);
    }

    /**
     * Dosen boleh mengoreksi sampai akhir hari; setelahnya hanya dosen wali atau admin (P-04).
     */
    public function canEdit(AttendanceSession $session, User $actor): bool
    {
        if ($actor->hasRole(Role::Admin)) {
            return true;
        }

        $teacherId = $actor->teacher?->id;

        if ($teacherId === null) {
            return false;
        }

        $isHomeroom = $session->studyGroup->homeroom_teacher_id === $teacherId;

        if ($isHomeroom) {
            return true;
        }

        $teachesIt = $session->schedule?->assignment?->teacher_id === $teacherId
            || $session->opened_by === $actor->id;

        return $teachesIt && $session->date->isToday();
    }

    private function authorizeEdit(AttendanceSession $session, User $actor): void
    {
        if (! $this->canEdit($session, $actor)) {
            throw new AuthorizationException('Kamu tidak berhak mengubah presensi ini.');
        }
    }

    private function createSession(StudyGroup $group, User $opener, AttendanceMode $mode, ?Schedule $schedule): AttendanceSession
    {
        return DB::transaction(function () use ($group, $opener, $mode, $schedule): AttendanceSession {
            $session = AttendanceSession::query()->firstOrCreate(
                ['schedule_id' => $schedule?->id, 'study_group_id' => $group->id, 'date' => today()->toDateString()],
                ['opened_by' => $opener->id, 'mode' => $mode, 'opened_at' => now()],
            );

            if ($session->wasRecentlyCreated || $session->records()->doesntExist()) {
                $onLeave = $this->studentsOnApprovedLeave($group);
                $rows = $group->activeStudents()->pluck('students.id')->map(fn (int $studentId): array => [
                    'attendance_session_id' => $session->id,
                    'student_id' => $studentId,
                    'status' => ($onLeave[$studentId] ?? AttendanceStatus::Present)->value,
                    'method' => 'auto',
                    'recorded_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->all();

                AttendanceRecord::query()->insert($rows);
            }

            return $session;
        });
    }

    /**
     * Mahasiswa dengan izin/sakit yang disetujui hari ini otomatis berstatus Izin/Sakit (P-14).
     *
     * @return array<int, AttendanceStatus>
     */
    private function studentsOnApprovedLeave(StudyGroup $group): array
    {
        return LeaveRequest::query()
            ->where('subject_type', 'student')
            ->where('status', 'disetujui')
            ->whereDate('starts_on', '<=', today())
            ->whereDate('ends_on', '>=', today())
            ->get()
            ->mapWithKeys(fn ($leave): array => [
                $leave->subject_id => $leave->type->value === 'sakit' ? AttendanceStatus::Sick : AttendanceStatus::Excused,
            ])
            ->all();
    }
}
