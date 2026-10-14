<?php

namespace App\Services;

use App\Models\Curriculum;
use App\Models\CurriculumSubject;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Support\Collection;

/**
 * Kurikulum per prodi dan angkatan: mata kuliah wajib atau pilihan per semester, serta prasyarat.
 */
class CurriculumService
{
    public function __construct(private readonly AcademicRecord $records) {}

    /**
     * Kurikulum yang berlaku bagi angkatan mahasiswa: yang aktif, mencakup angkatannya, dan paling baru.
     */
    public function forStudent(Student $student): ?Curriculum
    {
        return Curriculum::query()
            ->where('program_id', $student->program_id)
            ->where('is_active', true)
            ->orderByDesc('batch_from')
            ->get()
            ->first(fn (Curriculum $curriculum): bool => $curriculum->appliesToBatch((int) $student->batch_year));
    }

    /**
     * Mata kuliah kurikulum mahasiswa, berkunci id mata kuliah.
     *
     * @return Collection<int, CurriculumSubject>
     */
    public function itemsFor(Student $student): Collection
    {
        $curriculum = $this->forStudent($student);

        return $curriculum
            ? $curriculum->items()->with('subject.prerequisites')->get()->keyBy('subject_id')
            : collect();
    }

    /**
     * Prasyarat yang belum lulus untuk mengambil suatu mata kuliah.
     *
     * @param  Collection<int, int>  $passedSubjectIds
     * @return Collection<int, Subject>
     */
    public function unmetPrerequisites(Subject $subject, Collection $passedSubjectIds): Collection
    {
        return $subject->prerequisites->reject(fn (Subject $prerequisite): bool => $passedSubjectIds->contains($prerequisite->id))->values();
    }

    /**
     * Mata kuliah yang sudah lulus untuk prasyarat: hasil terbaik bukan E (angka mutu lebih dari nol).
     *
     * @return Collection<int, int>
     */
    public function passedForPrerequisites(Student $student): Collection
    {
        return $this->records->attempts($student)
            ->groupBy(fn ($grade): int => $grade->assignment->subject_id)
            ->filter(fn (Collection $attempts): bool => (float) ($this->records->best($attempts)?->points ?? 0) > 0)
            ->keys();
    }
}
