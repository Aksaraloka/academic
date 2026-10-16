<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Models\AcademicTerm;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\StudyGroup;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherCheckin;
use App\Models\TeachingAssignment;
use App\Models\TimeSlot;
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
     * Analitik kehadiran satu mahasiswa per mata kuliah yang diikuti kelasnya pada semester aktif.
     *
     * Status memakai proyeksi: jumlah pertemuan satu semester ($plannedMeetings) dipakai untuk menghitung
     * berapa kali masih boleh tidak hadir sebelum persentase 75% (atau $minPercent) tak mungkin tercapai.
     *
     * @return Collection<int, array{
     *     assignment: TeachingAssignment, subject: Subject, teacher: Teacher, slots: Collection<int, TimeSlot>,
     *     counts: array<string, int>, total: int, attended: int, missed: int, percent: ?int,
     *     planned: int, allowed: int, remaining: int, level: string, history: Collection<int, array{date: CarbonInterface, status: AttendanceStatus, note: ?string}>
     * }>
     */
    public function studentCourseAnalytics(Student $student, StudyGroup $group, AcademicTerm $term, int $minPercent, int $plannedMeetings): Collection
    {
        $assignments = TeachingAssignment::query()
            ->with(['subject', 'teacher', 'schedules.timeSlot'])
            ->where('study_group_id', $group->id)
            ->where('academic_term_id', $term->id)
            ->get();

        $scheduleToAssignment = $assignments
            ->flatMap(fn (TeachingAssignment $assignment) => $assignment->schedules->mapWithKeys(fn (Schedule $schedule) => [$schedule->id => $assignment->id]));

        $records = AttendanceRecord::query()
            ->with('session:id,date,schedule_id')
            ->where('student_id', $student->id)
            ->whereHas('session', fn ($query) => $query
                ->whereIn('schedule_id', $scheduleToAssignment->keys())
                ->whereBetween('date', [$term->starts_at->toDateString(), $term->ends_at->toDateString()]))
            ->get()
            ->groupBy(fn (AttendanceRecord $record): int => $scheduleToAssignment[$record->session->schedule_id]);

        return $assignments->map(function (TeachingAssignment $assignment) use ($records, $minPercent, $plannedMeetings): array {
            $rows = $records->get($assignment->id, collect())->sortBy(fn (AttendanceRecord $record) => $record->session->date)->values();

            return [
                'assignment' => $assignment,
                'subject' => $assignment->subject,
                'teacher' => $assignment->teacher,
                'slots' => $assignment->schedules->map->timeSlot->sortBy(fn (TimeSlot $slot) => $slot->day.$slot->starts_at)->values(),
                ...$this->evaluate($rows, $minPercent, $plannedMeetings),
                'history' => $rows->map(fn (AttendanceRecord $record): array => [
                    'date' => $record->session->date,
                    'status' => $record->status,
                    'note' => $record->note,
                ]),
            ];
        })->sortBy(fn (array $row): string => $row['subject']->name)->values();
    }

    /**
     * Rekap kehadiran satu kuliah untuk banyak mahasiswa sekaligus (dipakai API integrasi). Memakai aturan yang sama
     * dengan analitik mahasiswa di atas: hanya sesi berjadwal kuliah ini pada rentang semester, dengan proyeksi
     * pertemuan dan tingkat aman/waspada/risiko yang sama.
     *
     * @param  iterable<int, int>  $studentIds
     * @return array<int, array{counts: array<string, int>, total: int, attended: int, missed: int, percent: ?int, planned: int, allowed: int, remaining: int, level: string}> dikunci id mahasiswa
     */
    public function assignmentRecap(TeachingAssignment $assignment, iterable $studentIds, int $minPercent, int $plannedMeetings): array
    {
        $studentIds = collect($studentIds)->values();
        $term = $assignment->academicTerm;

        $records = AttendanceRecord::query()
            ->with('session:id,date,schedule_id')
            ->whereIn('student_id', $studentIds)
            ->whereHas('session', fn ($query) => $query
                ->whereIn('schedule_id', $assignment->schedules()->select('schedules.id'))
                ->whereBetween('date', [$term->starts_at->toDateString(), $term->ends_at->toDateString()]))
            ->get()
            ->groupBy('student_id');

        return $studentIds
            ->mapWithKeys(fn (int $studentId): array => [$studentId => $this->evaluate($records->get($studentId, collect()), $minPercent, $plannedMeetings)])
            ->all();
    }

    /**
     * Hitung status kehadiran dari baris presensi seorang mahasiswa pada satu kuliah. Satu-satunya tempat aturan
     * proyeksi pertemuan dan tingkat aman/waspada/risiko.
     *
     * @param  Collection<int, AttendanceRecord>  $rows
     * @return array{counts: array<string, int>, total: int, attended: int, missed: int, percent: ?int, planned: int, allowed: int, remaining: int, level: string}
     */
    private function evaluate(Collection $rows, int $minPercent, int $plannedMeetings): array
    {
        $counts = collect(AttendanceStatus::cases())
            ->mapWithKeys(fn (AttendanceStatus $status): array => [$status->value => $rows->where('status', $status)->count()])
            ->all();

        $total = $rows->count();
        $attended = $counts[AttendanceStatus::Present->value] + $counts[AttendanceStatus::Late->value];
        $missed = $total - $attended;
        $planned = max($plannedMeetings, $total);
        $allowed = (int) floor($planned * (100 - $minPercent) / 100);
        $remaining = max($allowed - $missed, 0);

        $level = match (true) {
            $total === 0 => 'baru',
            $missed > $allowed => 'risiko',
            $remaining <= 1 => 'waspada',
            default => 'aman',
        };

        return [
            'counts' => $counts,
            'total' => $total,
            'attended' => $attended,
            'missed' => $missed,
            'percent' => $total > 0 ? (int) round($attended / $total * 100) : null,
            'planned' => $planned,
            'allowed' => $allowed,
            'remaining' => $remaining,
            'level' => $level,
        ];
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
