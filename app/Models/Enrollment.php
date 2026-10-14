<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\EnrollmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tenant_id', 'academic_term_id', 'student_id', 'status', 'submitted_at', 'decided_by', 'decided_at', 'advisor_note'])]
class Enrollment extends Model
{
    /** @use HasFactory<EnrollmentFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => EnrollmentStatus::class,
            'submitted_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(EnrollmentItem::class);
    }

    public function assignments(): BelongsToMany
    {
        return $this->belongsToMany(TeachingAssignment::class, 'enrollment_items')->withTimestamps();
    }

    public function credits(): int
    {
        return (int) $this->assignments->sum(fn (TeachingAssignment $assignment): int => (int) $assignment->subject->credits);
    }
}
