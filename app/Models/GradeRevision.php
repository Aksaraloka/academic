<?php

namespace App\Models;

use App\Enums\GradeRevisionStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'grade_id', 'requested_by', 'reason', 'old_scores', 'new_scores', 'status', 'decided_by', 'decided_at', 'decision_note'])]
class GradeRevision extends Model
{
    use BelongsToTenant;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_scores' => 'array',
            'new_scores' => 'array',
            'status' => GradeRevisionStatus::class,
            'decided_at' => 'datetime',
        ];
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
