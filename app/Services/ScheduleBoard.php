<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Enums\Role;
use App\Models\AcademicTerm;
use App\Models\Enrollment;
use App\Models\Schedule;
use App\Models\StudyGroup;
use App\Models\StudyGroupMember;
use App\Models\TeachingAssignment;
use App\Models\TimeSlot;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Menyusun data papan jadwal mingguan (dipakai halaman Jadwal dan unduhan PDF).
 */
class ScheduleBoard
{
    /**
     * Semua semester milik kampus, terbaru lebih dulu, berkunci id.
     *
     * @return Collection<int, AcademicTerm>
     */
    public function terms(): Collection
    {
        return AcademicTerm::query()->orderByDesc('starts_at')->get()->keyBy('id');
    }

    /**
     * Semester yang dipilih; bila tidak valid, semester aktif lalu semester terbaru.
     *
     * @param  Collection<int, AcademicTerm>  $terms
     */
    public function resolveTerm(Collection $terms, int|string|null $requested): ?AcademicTerm
    {
        return $terms->get((int) $requested)
            ?? $terms->first(fn (AcademicTerm $term): bool => $term->is_active)
            ?? $terms->first();
    }

    /**
     * Pilihan awal menurut peran: dosen melihat jadwalnya, mahasiswa dan orang tua melihat kelas anaknya
     * pada semester itu (kelas berbeda tiap semester), staf lain melihat kelas pertama.
     *
     * @return array{view: string, id: string}
     */
    public function defaultTarget(User $user, int $termId, string $view = 'group'): array
    {
        if ($user->hasRole(Role::Teacher, Role::Duty) && $user->teacher) {
            return ['view' => 'teacher', 'id' => (string) $user->teacher->id];
        }

        $student = $user->hasRole(Role::Student)
            ? $user->student
            : ($user->hasRole(Role::Guardian) ? $user->guardian?->students()->first() : null);

        if ($student) {
            return ['view' => 'student', 'id' => (string) $student->id];
        }

        if ($view === 'group') {
            return ['view' => 'group', 'id' => (string) (StudyGroup::query()->where('academic_term_id', $termId)->orderBy('name')->value('id') ?? '')];
        }

        return ['view' => $view, 'id' => ''];
    }

    /**
     * Kuliah yang diikuti mahasiswa pada semester itu: sesuai KRS yang disetujui; bila belum ada KRS, paket kelas angkatannya.
     *
     * @return array<int, int>
     */
    public function assignmentIdsForStudent(int $studentId, AcademicTerm $term): array
    {
        $enrolled = Enrollment::query()
            ->where('academic_term_id', $term->id)->where('student_id', $studentId)->where('status', EnrollmentStatus::Approved)
            ->with('items')->first();

        if ($enrolled && $enrolled->items->isNotEmpty()) {
            return $enrolled->items->pluck('teaching_assignment_id')->all();
        }

        $groupIds = StudyGroupMember::query()
            ->where('student_id', $studentId)
            ->whereHas('studyGroup', fn ($query) => $query->where('academic_term_id', $term->id))
            ->pluck('study_group_id');

        return TeachingAssignment::query()->where('academic_term_id', $term->id)->whereIn('study_group_id', $groupIds)->pluck('id')->all();
    }

    /**
     * @return array{
     *     schedules: Collection<int, Schedule>, periods: Collection<int, TimeSlot>, days: Collection<int, string>,
     *     grid: Collection<string, Collection<int, Schedule>>, byDay: Collection<int, Collection<int, Schedule>>,
     *     subjects: Collection<int, TeachingAssignment>, stats: array{meetings: int, courses: int, minutes: int}
     * }
     */
    public function build(?AcademicTerm $term, string $view, string $targetId): array
    {
        $schedules = collect();

        if ($term && $targetId !== '') {
            $schedules = Schedule::query()
                ->with(['assignment.teacher', 'assignment.subject', 'assignment.studyGroup', 'timeSlot', 'room'])
                ->whereHas('assignment', fn ($query) => $query->where('academic_term_id', $term->id))
                ->when($view === 'teacher', fn ($query) => $query->whereHas('assignment', fn ($inner) => $inner->where('teacher_id', $targetId)))
                ->when($view === 'room', fn ($query) => $query->where('room_id', $targetId))
                ->when($view === 'group', fn ($query) => $query->whereHas('assignment', fn ($inner) => $inner->where('study_group_id', $targetId)))
                ->when($view === 'student', fn ($query) => $query->whereIn('teaching_assignment_id', $this->assignmentIdsForStudent((int) $targetId, $term)))
                ->get();
        }

        $slots = TimeSlot::query()->where('is_break', false)->orderBy('day')->orderBy('period_number')->get();
        $periods = $slots->groupBy('period_number')->map(fn (Collection $group): TimeSlot => $group->sortBy('day')->first())->sortKeys();

        return [
            'schedules' => $schedules,
            'periods' => $periods,
            // Hari kuliah Senin sampai Sabtu selalu ditampilkan.
            'days' => collect(TimeSlot::DAYS),
            'grid' => $schedules->groupBy(fn (Schedule $schedule): string => $schedule->timeSlot->period_number.'-'.$schedule->timeSlot->day),
            'byDay' => $schedules->sortBy(fn (Schedule $schedule): string => $schedule->timeSlot->starts_at)->groupBy(fn (Schedule $schedule): int => $schedule->timeSlot->day),
            'subjects' => $schedules->map(fn (Schedule $schedule) => $schedule->assignment)->unique('subject_id')->sortBy(fn ($assignment) => $assignment->subject->name)->values(),
            'stats' => [
                'meetings' => $schedules->count(),
                'courses' => $schedules->pluck('assignment.subject_id')->unique()->count(),
                'minutes' => $schedules->sum(fn (Schedule $schedule): int => Carbon::parse($schedule->timeSlot->starts_at)->diffInMinutes(Carbon::parse($schedule->timeSlot->ends_at))),
            ],
        ];
    }
}
