<?php

namespace App\Services;

use App\Enums\GradeKind;
use App\Enums\Role;
use App\Models\AcademicTerm;
use App\Models\Grade;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Rekam akademik mahasiswa: KHS per semester, IPS, IPK, dan transkrip.
 * Hanya nilai yang sudah dikunci dosen yang dihitung. Untuk mata kuliah yang diulang atau diremedial,
 * yang dipakai adalah hasil terbaik.
 */
class AcademicRecord
{
    /**
     * Mahasiswa yang boleh dilihat pengguna: dirinya, anaknya (orang tua), atau siapa pun bagi BAAK, pimpinan, dan wakil dekan.
     */
    public function resolveStudent(User $user, ?int $requestedStudentId = null, ?int $requestedChildId = null): ?Student
    {
        if ($user->hasRole(Role::Student)) {
            return $user->student;
        }

        if ($user->hasRole(Role::Guardian)) {
            $children = $user->guardian?->students()->get() ?? collect();

            return $children->firstWhere('id', $requestedChildId) ?? $children->first();
        }

        if ($user->hasRole(Role::Admin, Role::Principal, Role::Curriculum) && $requestedStudentId) {
            return Student::query()->find($requestedStudentId);
        }

        return null;
    }

    /**
     * Semua percobaan bernilai (reguler dan remedial) dari kuliah yang nilainya sudah dikunci.
     *
     * @return Collection<int, Grade>
     */
    public function attempts(Student $student): Collection
    {
        return Grade::query()
            ->with(['assignment.subject', 'assignment.academicTerm'])
            ->where('student_id', $student->id)
            ->whereNotNull('total_score')
            ->whereHas('assignment', fn ($query) => $query->whereNotNull('grades_locked_at'))
            ->get();
    }

    /**
     * Percobaan terbaik: angka mutu tertinggi, lalu nilai angka tertinggi, lalu semester terbaru.
     *
     * @param  Collection<int, Grade>  $attempts
     */
    public function best(Collection $attempts): ?Grade
    {
        return $attempts
            ->sortBy([
                fn (Grade $a, Grade $b): int => (float) $b->points <=> (float) $a->points,
                fn (Grade $a, Grade $b): int => (float) $b->total_score <=> (float) $a->total_score,
                fn (Grade $a, Grade $b): int => $b->assignment->academicTerm->starts_at <=> $a->assignment->academicTerm->starts_at,
            ])
            ->first();
    }

    /**
     * KHS satu semester.
     *
     * @return array{term: AcademicTerm, rows: Collection<int, array<string, mixed>>, pending: Collection<int, array<string, mixed>>, credits: int, quality_points: float, ips: ?float, cumulative: array<string, mixed>}
     */
    public function khs(Student $student, AcademicTerm $term): array
    {
        $all = $this->attempts($student);
        $inTerm = $all->filter(fn (Grade $grade): bool => $grade->assignment->academic_term_id === $term->id);

        $rows = $inTerm
            ->groupBy(fn (Grade $grade): int => $grade->assignment->subject_id)
            ->map(function (Collection $group) use ($all): array {
                $best = $this->best($group);

                return $this->row($best, $this->takenInManyTerms($all, $best->assignment->subject_id), $group->count() > 1);
            })
            ->sortBy(fn (array $row): string => $row['subject']->name)
            ->values();

        $credits = (int) $rows->sum('credits');
        $quality = (float) $rows->sum('mutu');

        $pending = Grade::query()
            ->with(['assignment.subject'])
            ->where('student_id', $student->id)
            ->where('kind', GradeKind::Regular)
            ->whereHas('assignment', fn ($query) => $query->where('academic_term_id', $term->id)->whereNull('grades_locked_at'))
            ->get()
            ->map(fn (Grade $grade): array => ['subject' => $grade->assignment->subject, 'credits' => (int) $grade->assignment->subject->credits]);

        return [
            'term' => $term,
            'rows' => $rows,
            'pending' => $pending,
            'credits' => $credits,
            'quality_points' => $quality,
            'ips' => $credits > 0 ? round($quality / $credits, 2) : null,
            'cumulative' => $this->transcript($student),
        ];
    }

    /**
     * Transkrip: hasil terbaik tiap mata kuliah di seluruh semester, ditambah nilai riwayat sebelum sistem dipakai.
     *
     * @return array{rows: Collection<int, array<string, mixed>>, groups: Collection<int, Collection<int, array<string, mixed>>>, credits_counted: int, credits_passed: int, quality_points: float, ipk: ?float, legacy_credits: int, legacy_quality_points: float}
     */
    public function transcript(Student $student): array
    {
        $attempts = $this->attempts($student);

        $rows = $attempts
            ->groupBy(fn (Grade $grade): int => $grade->assignment->subject_id)
            ->map(fn (Collection $group): array => $this->row($this->best($group), $this->takenInManyTerms($attempts, $group->first()->assignment->subject_id), $group->count() > 1))
            ->sortBy([
                fn (array $a, array $b): int => $a['term']->starts_at <=> $b['term']->starts_at,
                fn (array $a, array $b): int => strcmp($a['subject']->name, $b['subject']->name),
            ])
            ->values();

        $legacyCredits = (int) $student->legacy_credits;
        $legacyQuality = (float) $student->legacy_quality_points;
        $counted = (int) $rows->sum('credits') + $legacyCredits;
        $quality = (float) $rows->sum('mutu') + $legacyQuality;
        $passed = (int) $rows->where('passed', true)->sum('credits') + $legacyCredits;

        return [
            'rows' => $rows,
            'groups' => $rows->groupBy(fn (array $row): int => $row['term']->id),
            'credits_counted' => $counted,
            'credits_passed' => $passed,
            'quality_points' => $quality,
            'ipk' => $counted > 0 ? round($quality / $counted, 2) : null,
            'legacy_credits' => $legacyCredits,
            'legacy_quality_points' => $legacyQuality,
        ];
    }

    /**
     * Semester yang punya baris nilai bagi mahasiswa (dikunci atau belum), terbaru dahulu.
     *
     * @return Collection<int, AcademicTerm>
     */
    public function termsWithGrades(Student $student): Collection
    {
        $termIds = Grade::query()
            ->where('student_id', $student->id)
            ->with('assignment:id,academic_term_id')
            ->get()
            ->pluck('assignment.academic_term_id')
            ->unique();

        return AcademicTerm::query()->whereIn('id', $termIds)->orderByDesc('starts_at')->get();
    }

    /**
     * Semester untuk KHS: yang diminta bila valid, selain itu semester terbaru yang nilainya sudah dikunci dosen,
     * lalu semester terbaru yang punya baris nilai.
     */
    public function defaultTerm(Student $student, ?int $requestedId = null): ?AcademicTerm
    {
        $terms = $this->termsWithGrades($student);

        if ($requestedId !== null && ($requested = $terms->firstWhere('id', $requestedId))) {
            return $requested;
        }

        $released = $this->attempts($student)->pluck('assignment.academic_term_id')->unique();

        return $terms->first(fn (AcademicTerm $term): bool => $released->contains($term->id)) ?? $terms->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Grade $grade, bool $repeated, bool $multiple): array
    {
        $subject = $grade->assignment->subject;
        $credits = (int) $subject->credits;
        $points = (float) $grade->points;

        return [
            'grade' => $grade,
            'subject' => $subject,
            'term' => $grade->assignment->academicTerm,
            'kind' => $grade->kind,
            'total' => (float) $grade->total_score,
            'letter' => $grade->letter,
            'points' => $points,
            'credits' => $credits,
            'mutu' => round($points * $credits, 2),
            'passed' => $points >= 1.0,
            'repeated' => $repeated,
            'best_of_many' => $multiple,
        ];
    }

    /**
     * Apakah mata kuliah ini diambil di lebih dari satu semester (mengulang).
     *
     * @param  Collection<int, Grade>  $attempts
     */
    private function takenInManyTerms(Collection $attempts, int $subjectId): bool
    {
        return $attempts
            ->filter(fn (Grade $grade): bool => $grade->assignment->subject_id === $subjectId)
            ->pluck('assignment.academic_term_id')
            ->unique()
            ->count() > 1;
    }

    /**
     * Ringkasan kelayakan wisuda dari transkrip.
     *
     * @return array{credits: int, gpa: ?float, has_grades: bool}
     */
    public function eligibility(Student $student): array
    {
        $transcript = $this->transcript($student);

        return [
            'credits' => $transcript['credits_passed'],
            'gpa' => $transcript['ipk'],
            'has_grades' => $transcript['credits_counted'] > 0,
        ];
    }
}
