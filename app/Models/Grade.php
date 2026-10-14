<?php

namespace App\Models;

use App\Enums\GradeKind;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tenant_id', 'teaching_assignment_id', 'student_id', 'kind', 'assignment_score', 'midterm_score', 'final_exam_score', 'attendance_score', 'total_score', 'letter', 'points'])]
class Grade extends Model
{
    use BelongsToTenant;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => GradeKind::class,
            'assignment_score' => 'decimal:2',
            'midterm_score' => 'decimal:2',
            'final_exam_score' => 'decimal:2',
            'attendance_score' => 'decimal:2',
            'total_score' => 'decimal:2',
            'points' => 'decimal:2',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(TeachingAssignment::class, 'teaching_assignment_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(GradeRevision::class);
    }

    /**
     * Komponen nilai sebagai larik float (null bila belum diisi).
     *
     * @return array{assignment: ?float, midterm: ?float, final: ?float, attendance: ?float, total: ?float, letter: ?string}
     */
    public function scores(): array
    {
        return [
            'assignment' => $this->assignment_score === null ? null : (float) $this->assignment_score,
            'midterm' => $this->midterm_score === null ? null : (float) $this->midterm_score,
            'final' => $this->final_exam_score === null ? null : (float) $this->final_exam_score,
            'attendance' => $this->attendance_score === null ? null : (float) $this->attendance_score,
            'total' => $this->total_score === null ? null : (float) $this->total_score,
            'letter' => $this->letter,
        ];
    }

    public function hasComponents(): bool
    {
        return $this->assignment_score !== null && $this->midterm_score !== null && $this->final_exam_score !== null;
    }
}
