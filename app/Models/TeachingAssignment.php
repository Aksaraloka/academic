<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tenant_id', 'academic_term_id', 'teacher_id', 'subject_id', 'study_group_id', 'section', 'capacity', 'hours_per_week', 'weight_assignment', 'weight_midterm', 'weight_final', 'weight_attendance', 'grades_locked_at', 'grades_locked_by', 'grading_extended_until'])]
class TeachingAssignment extends Model
{
    use BelongsToTenant;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'grades_locked_at' => 'datetime',
            'grading_extended_until' => 'date',
        ];
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }

    /**
     * Nama kelas untuk tampilan: kelas angkatan, ditambah seksi bila kuliah dibuka paralel.
     */
    public function classLabel(): string
    {
        return $this->studyGroup->name.($this->section ? ' · Seksi '.$this->section : '');
    }

    public function areGradesLocked(): bool
    {
        return $this->grades_locked_at !== null;
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function studyGroup(): BelongsTo
    {
        return $this->belongsTo(StudyGroup::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }
}
