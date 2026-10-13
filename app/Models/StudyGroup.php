<?php

namespace App\Models;

use App\Enums\Role;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tenant_id', 'academic_term_id', 'grade_level_id', 'program_id', 'homeroom_teacher_id', 'name'])]
class StudyGroup extends Model
{
    use BelongsToTenant, HasFactory;

    /**
     * Kelas yang boleh dilihat: semua untuk admin/pimpinan, kelas perwalian dan kelas ajar untuk dosen.
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->hasRole(Role::Teacher, Role::Duty) && $user->teacher) {
            $teacherId = $user->teacher->id;

            $query->where(fn (Builder $inner) => $inner
                ->where('homeroom_teacher_id', $teacherId)
                ->orWhereIn('id', TeachingAssignment::query()->where('teacher_id', $teacherId)->select('study_group_id')));
        } elseif (! $user->hasRole(Role::Admin, Role::Principal, Role::Curriculum, Role::Teacher, Role::Duty)) {
            $query->whereRaw('1 = 0');
        }
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class);
    }

    public function homeroomTeacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'homeroom_teacher_id');
    }

    public function teachingAssignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(StudyGroupMember::class);
    }

    /**
     * Mahasiswa yang saat ini masih tercatat di kelas.
     */
    public function activeStudents(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'study_group_members')
            ->wherePivotNull('left_at')
            ->withPivot(['joined_at', 'left_at']);
    }
}
