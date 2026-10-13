<?php

namespace App\Models;

use App\Enums\GraduationStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'graduation_period_id', 'student_id', 'thesis_title', 'gpa', 'credits_earned', 'defense_date', 'gown_size', 'status', 'rejection_reason', 'verified_by', 'verified_at'])]
class GraduationRegistration extends Model
{
    use BelongsToTenant;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => GraduationStatus::class,
            'gpa' => 'decimal:2',
            'defense_date' => 'date',
            'verified_at' => 'datetime',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(GraduationPeriod::class, 'graduation_period_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
