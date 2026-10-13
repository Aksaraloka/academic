<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\StudyGroup;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherCheckin;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class AttendanceReport
{
    /**
     * Jadwal yang jatuh pada tanggal tertentu (1 = Senin ... 7 = Minggu).
     *
     * @return Collection<int, Schedule>
     */
    public function schedulesOn(CarbonInterface $date, ?int $teacherId = null, ?int $groupId = null): Collection
    {
        return Schedule::query()
            ->with(['assignment.teacher', 'assignment.subject', 'assignment.studyGroup', 'timeSlot', 'room', 'overrides' => fn ($query) => $query->whereDate('date', $date)])
            ->whereHas('timeSlot', fn ($query) => $query->where('day', $date->dayOfWeekIso)->where('is_break', false))
            ->whereHas('assignment.studyGroup.academicTerm', fn ($query) => $query->where('is_active', true))
            ->when($teacherId, fn ($query) => $query->whereHas('assignment', fn ($inner) => $inner->where('teacher_id', $teacherId)))
            ->when($groupId, fn ($query) => $query->whereHas('assignment', fn ($inner) => $inner->where('study_group_id', $groupId)))
            ->get()
            ->sortBy(fn (Schedule $schedule): string => $schedule->timeSlot->starts_at)
            ->values();
    }

    /**
     * Ringkasan presensi mahasiswa untuk hari tertentu (P-19).
     *
     * @return array{present: int, total: int, percent: ?int, scheduled: int, opened: int, unfilled: int}
     */
    public function dayOverview(CarbonInterface $date): array
    {
        $counts = AttendanceRecord::query()
            ->whereHas('session', fn ($query) => $query->whereDate('date', $date))
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $total = (int) $counts->sum();
        $present = (int) ($counts[AttendanceStatus::Present->value] ?? 0) + (int) ($counts[AttendanceStatus::Late->value] ?? 0);

        $scheduled = $this->schedulesOn($date)->count();
        $opened = AttendanceSession::query()->whereDate('date', $date)->whereNotNull('schedule_id')->count();

        return [
            'present' => $present,
            'total' => $total,
            'percent' => $total > 0 ? (int) round($present / $total * 100) : null,
            'scheduled' => $scheduled,
            'opened' => $opened,
            'unfilled' => max($scheduled - $opened, 0),
        ];
    }

    /**
     * Dosen yang belum check-in pada tanggal tertentu.
     *
     * @return Collection<int, Teacher>
     */
    public function teachersNotCheckedIn(CarbonInterface $date): Collection
    {
        $checkedIn = TeacherCheckin::query()->whereDate('date', $date)->whereNotNull('check_in_at')->pluck('teacher_id');

        return Teacher::query()->whereNotIn('id', $checkedIn)->orderBy('name')->get();
    }

    /**
     * Rekap per mahasiswa dalam satu kelas pada rentang tanggal (P-17).
     *
     * @return Collection<int, array{student: Student, counts: array<string, int>, total: int, percent: ?int}>
     */
    public function groupRecap(StudyGroup $group, CarbonInterface $from, CarbonInterface $to, ?int $subjectId = null): Collection
    {
        $students = $group->activeStudents()->orderBy('students.name')->get();

        $records = AttendanceRecord::query()
            ->whereIn('student_id', $students->pluck('id'))
            ->whereHas('session', fn ($query) => $this->sessionScope($query, $group, $from, $to, $subjectId))
            ->get()
            ->groupBy('student_id');

        return $students->map(function ($student) use ($records): array {
            $studentRecords = $records->get($student->id, collect());
            $counts = collect(AttendanceStatus::cases())
                ->mapWithKeys(fn (AttendanceStatus $status): array => [
                    $status->value => $studentRecords->where('status', $status)->count(),
                ])->all();

            $total = $studentRecords->count();
            $attended = $counts[AttendanceStatus::Present->value] + $counts[AttendanceStatus::Late->value];

            return [
                'student' => $student,
                'counts' => $counts,
                'total' => $total,
                'percent' => $total > 0 ? (int) round($attended / $total * 100) : null,
            ];
        });
    }

    /**
     * Riwayat kehadiran satu mahasiswa.
     *
     * @return array{counts: array<string, int>, total: int, percent: ?int}
     */
    public function studentSummary(int $studentId): array
    {
        $records = AttendanceRecord::query()->where('student_id', $studentId)->get();
        $counts = collect(AttendanceStatus::cases())
            ->mapWithKeys(fn (AttendanceStatus $status): array => [$status->value => $records->where('status', $status)->count()])
            ->all();
        $total = $records->count();
        $attended = $counts['hadir'] + $counts['terlambat'];

        return [
            'counts' => $counts,
            'total' => $total,
            'percent' => $total > 0 ? (int) round($attended / $total * 100) : null,
        ];
    }

    /**
     * Persentase kehadiran satu mahasiswa per mata kuliah, dasar syarat mengikuti ujian (P-16).
     *
     * @return Collection<int, array{subject: Subject, attended: int, total: int, percent: ?int}>
     */
    public function studentBySubject(int $studentId): Collection
    {
        return AttendanceRecord::query()
            ->with('session.schedule.assignment.subject')
            ->where('student_id', $studentId)
            ->whereHas('session', fn ($query) => $query->whereNotNull('schedule_id'))
            ->get()
            ->groupBy(fn (AttendanceRecord $record): int => $record->session->schedule->assignment->subject_id)
            ->map(function (Collection $rows): array {
                $attended = $rows->filter(fn ($row) => in_array($row->status, [AttendanceStatus::Present, AttendanceStatus::Late], true))->count();

                return [
                    'subject' => $rows->first()->session->schedule->assignment->subject,
                    'attended' => $attended,
                    'total' => $rows->count(),
                    'percent' => (int) round($attended / $rows->count() * 100),
                ];
            })
            ->sortBy(fn (array $row): string => $row['subject']->name)
            ->values();
    }

    private function sessionScope($query, StudyGroup $group, CarbonInterface $from, CarbonInterface $to, ?int $subjectId): void
    {
        $query->where('study_group_id', $group->id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->when($subjectId, fn ($inner) => $inner->whereHas('schedule.assignment', fn ($assignment) => $assignment->where('subject_id', $subjectId)));
    }

    /**
     * Mahasiswa dengan alpa >= ambang pada rentang tanggal (P-15), dihitung per hari berbeda.
     *
     * @return Collection<int, array{student: Student, days: int}>
     */
    public function repeatedAbsences(StudyGroup $group, CarbonInterface $from, CarbonInterface $to, int $threshold, ?int $subjectId = null): Collection
    {
        return AttendanceRecord::query()
            ->with('student')
            ->where('status', AttendanceStatus::Absent)
            ->whereHas('session', fn ($query) => $this->sessionScope($query, $group, $from, $to, $subjectId))
            ->get()
            ->groupBy('student_id')
            ->map(fn (Collection $rows): array => [
                'student' => $rows->first()->student,
                'days' => $rows->map(fn ($row) => $row->session->date->toDateString())->unique()->count(),
            ])
            ->filter(fn (array $row): bool => $row['days'] >= $threshold)
            ->sortByDesc('days')
            ->values();
    }
}
