<?php

namespace App\Models;

use App\Enums\StatusRequestStatus;
use App\Enums\StatusRequestType;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\StatusRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'student_id', 'academic_term_id', 'type', 'reason', 'status', 'decided_by', 'decided_at', 'decision_note'])]
class StatusRequest extends Model
{
    /** @use HasFactory<StatusRequestFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => StatusRequestType::class,
            'status' => StatusRequestStatus::class,
            'decided_at' => 'datetime',
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

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
