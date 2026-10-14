<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\ExamType;
use App\Enums\StudentStatus;
use App\Models\AcademicTerm;
use App\Models\AttendanceRecord;
use App\Models\ExamSchedule;
use App\Models\Student;
use App\Models\TeachingAssignment;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Jadwal UTS dan UAS, serta kelayakan mahasiswa mengikuti ujian: kehadiran minimal per mata kuliah (bawaan 75%),
 * status mahasiswa aktif, dan tagihan lunas bila kampus mewajibkannya.
 */
class ExamService
{
    public function __construct(
        private readonly ClassRoster $roster,
        private readonly ScheduleBoard $board,
        private readonly FinanceService $finance,
        private readonly AcademicCalendar $calendar,
    ) {}

    /**
     * Persentase kehadiran tiap mahasiswa pada satu kuliah, dihitung dari pertemuan yang sudah dipresensi.
     * Null berarti belum ada pertemuan yang dicatat.
     *
     * @param  Collection<int, int>  $studentIds
     * @return array<int, array{held: int, attended: int, percent: ?int}>
     */
    public function attendance(TeachingAssignment $assignment, Collection $studentIds): array
    {
        $records = AttendanceRecord::query()
            ->whereIn('student_id', $studentIds)
            ->whereHas('session.schedule', fn ($query) => $query->where('teaching_assignment_id', $assignment->id))
            ->get(['student_id', 'status'])
            ->groupBy('student_id');

        $result = [];

        foreach ($studentIds as $studentId) {
            $rows = $records->get($studentId, collect());
            $attended = $rows->filter(fn ($record): bool => in_array($record->status, [AttendanceStatus::Present, AttendanceStatus::Late], true))->count();
            $result[$studentId] = ['held' => $rows->count(), 'attended' => $attended, 'percent' => $rows->isEmpty() ? null : (int) round($attended / $rows->count() * 100)];
        }

        return $result;
    }

    public function minPercent(Student $student): int
    {
        return (int) $student->tenant->setting('min_attendance_percent');
    }

    /**
     * Peserta ujian satu jadwal beserta kelayakannya.
     *
     * @return Collection<int, array{student: Student, held: int, attended: int, percent: ?int, eligible: bool, reason: ?string}>
     */
    public function participants(ExamSchedule $exam): Collection
    {
        $assignment = $exam->assignment->loadMissing('academicTerm');
        $ids = $this->roster->studentIds($assignment);
        $students = Student::query()->whereIn('id', $ids)->orderBy('name')->get();
        $attendance = $this->attendance($assignment, $ids);

        return $students->map(function (Student $student) use ($attendance, $assignment): array {
            $row = $attendance[$student->id];
            [$eligible, $reason] = $this->verdict($student, $row['percent'], $assignment->academicTerm);

            return ['student' => $student] + $row + ['eligible' => $eligible, 'reason' => $reason];
        });
    }

    /**
     * Kuliah yang diikuti mahasiswa semester itu beserta jadwal ujian dan kelayakannya.
     *
     * @return Collection<int, array{assignment: TeachingAssignment, exam: ?ExamSchedule, percent: ?int, held: int, attended: int, eligible: bool, reason: ?string}>
     */
    public function cardRows(Student $student, AcademicTerm $term, ExamType $type): Collection
    {
        $ids = $this->board->assignmentIdsForStudent($student->id, $term);
        $assignments = TeachingAssignment::query()->with(['subject', 'teacher', 'studyGroup'])->whereIn('id', $ids)->get()->sortBy(fn ($a) => $a->subject->name);
        $exams = ExamSchedule::query()->with('room')->where('type', $type)->whereIn('teaching_assignment_id', $ids)->get()->keyBy('teaching_assignment_id');

        return $assignments->map(function (TeachingAssignment $assignment) use ($student, $term, $exams): array {
            $stats = $this->attendance($assignment, collect([$student->id]))[$student->id];
            [$eligible, $reason] = $this->verdict($student, $stats['percent'], $term);

            return ['assignment' => $assignment, 'exam' => $exams->get($assignment->id)] + $stats + ['eligible' => $eligible, 'reason' => $reason];
        })->values();
    }

    /**
     * Hambatan di luar kehadiran yang berlaku untuk seluruh kartu: status tidak aktif atau tagihan belum lunas.
     */
    public function cardBlocker(Student $student, AcademicTerm $term): ?string
    {
        if ($student->status !== StudentStatus::Active) {
            return "Status akademik {$student->status->label()}.";
        }

        $clearance = $this->finance->krsClearance($student, $term);

        return $clearance['allowed'] ? null : 'Tagihan semester ini belum lunas (sisa '.FinanceService::rupiah($clearance['outstanding']).').';
    }

    /**
     * @return array{0: bool, 1: ?string}
     */
    private function verdict(Student $student, ?int $percent, AcademicTerm $term): array
    {
        if ($blocker = $this->cardBlocker($student, $term)) {
            return [false, $blocker];
        }

        $min = $this->minPercent($student);

        if ($percent !== null && $percent < $min) {
            return [false, "Kehadiran {$percent}% di bawah syarat {$min}%."];
        }

        return [true, null];
    }

    /**
     * Validasi jadwal: dalam rentang semester, dalam agenda UTS/UAS kalender akademik bila ada,
     * serta tidak bentrok dengan ujian lain pada kelas atau ruang yang sama.
     *
     * @param  Collection<int, TeachingAssignment>  $assignments
     */
    public function validate(AcademicTerm $term, ExamType $type, CarbonInterface $date, string $startsAt, string $endsAt, ?int $roomId, Collection $assignments, ?int $ignoreExamId = null): void
    {
        if ($date->lt($term->starts_at) || $date->gt($term->ends_at->copy()->addDays(30))) {
            throw ValidationException::withMessages(['date' => 'Tanggal ujian di luar rentang semester ini.']);
        }

        $agenda = $this->calendar->entries($term)->where('type', $type->eventType());

        if ($agenda->isNotEmpty() && ! $agenda->contains(fn (array $entry): bool => $date->between($entry['starts_on'], $entry['ends_on']))) {
            $range = $agenda->map(fn (array $entry): string => $entry['starts_on']->translatedFormat('j M').'–'.$entry['ends_on']->translatedFormat('j M Y'))->implode(', ');

            throw ValidationException::withMessages(['date' => "Tanggal harus dalam masa {$type->label()} di kalender akademik ({$range})."]);
        }

        $others = ExamSchedule::query()->with('assignment.subject')->where('academic_term_id', $term->id)
            ->whereDate('date', $date->toDateString())
            ->when($ignoreExamId, fn ($query) => $query->whereKeyNot($ignoreExamId))
            ->get()
            ->filter(fn (ExamSchedule $other): bool => $other->starts_at < $endsAt && $startsAt < $other->ends_at);

        $messages = [];

        foreach ($assignments as $assignment) {
            foreach ($others as $other) {
                if ($other->assignment->study_group_id === $assignment->study_group_id && $other->teaching_assignment_id !== $assignment->id) {
                    $messages[] = "Kelas {$assignment->studyGroup->name} sudah punya ujian {$other->assignment->subject->name} pada jam ini.";
                }

                if ($roomId !== null && $other->room_id === $roomId) {
                    $messages[] = "Ruang sudah dipakai ujian {$other->assignment->subject->name} pada jam ini.";
                }
            }
        }

        if ($messages !== []) {
            throw ValidationException::withMessages(['date' => implode(' ', array_unique($messages))]);
        }
    }
}
