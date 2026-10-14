<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Enums\StudentStatus;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\StudyGroupMember;
use App\Models\TeachingAssignment;
use Illuminate\Support\Collection;

/**
 * Peserta satu kelas kuliah: anggota kelas angkatan, ditambah mahasiswa dengan KRS disetujui dari kelas lain,
 * dikurangi anggota yang pindah ke seksi lain mata kuliah yang sama lewat KRS-nya.
 */
class ClassRoster
{
    /**
     * @return Collection<int, int>
     */
    public function studentIds(TeachingAssignment $assignment, bool $activeOnly = false): Collection
    {
        $term = $assignment->academicTerm;

        $memberIds = StudyGroupMember::query()
            ->where('study_group_id', $assignment->study_group_id)
            ->whereDate('joined_at', '<=', $term->ends_at)
            ->where(fn ($query) => $query->whereNull('left_at')->orWhereDate('left_at', '>', $term->starts_at))
            ->pluck('student_id');

        $approved = Enrollment::query()
            ->where('academic_term_id', $assignment->academic_term_id)
            ->where('status', EnrollmentStatus::Approved)
            ->whereHas('items')
            ->with('items.assignment:id,subject_id')
            ->get();

        $enrolledIds = $approved
            ->filter(fn (Enrollment $enrollment): bool => $enrollment->items->contains('teaching_assignment_id', $assignment->id))
            ->pluck('student_id');

        $movedOutIds = $approved
            ->filter(fn (Enrollment $enrollment): bool => ! $enrollment->items->contains('teaching_assignment_id', $assignment->id)
                && $enrollment->items->contains(fn ($item): bool => $item->assignment?->subject_id === $assignment->subject_id))
            ->pluck('student_id');

        $ids = $memberIds->merge($enrolledIds)->unique()->diff($movedOutIds)->values();

        if ($activeOnly) {
            $ids = Student::query()->whereIn('id', $ids)->where('status', StudentStatus::Active)->pluck('id');
        }

        return $ids;
    }
}
