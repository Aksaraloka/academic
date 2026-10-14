<?php

namespace App\Models;

use App\Enums\ThesisStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['tenant_id', 'student_id', 'academic_term_id', 'title', 'abstract', 'status', 'decision_note', 'approved_by', 'approved_at', 'defense_consent_at', 'completed_at', 'final_score', 'final_letter'])]
class Thesis extends Model
{
    use BelongsToTenant;

    protected $table = 'theses';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ThesisStatus::class,
            'approved_at' => 'datetime',
            'defense_consent_at' => 'datetime',
            'completed_at' => 'datetime',
            'final_score' => 'decimal:2',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function supervisors(): HasMany
    {
        return $this->hasMany(ThesisSupervisor::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ThesisGuidanceLog::class)->orderByDesc('held_on')->orderByDesc('id');
    }

    public function defenses(): HasMany
    {
        return $this->hasMany(ThesisDefense::class)->orderByDesc('scheduled_on')->orderByDesc('id');
    }

    public function latestDefense(): HasOne
    {
        return $this->hasOne(ThesisDefense::class)->latestOfMany('scheduled_on');
    }

    public function primarySupervisor(): ?Teacher
    {
        return $this->supervisors->firstWhere('role', 'primary')?->teacher;
    }
}
