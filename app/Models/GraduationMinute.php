<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'graduation_period_id', 'number', 'issued_on', 'chair_name', 'chair_title', 'approver_name', 'approver_title', 'notes', 'participants', 'finalized_by', 'finalized_at'])]
class GraduationMinute extends Model
{
    use BelongsToTenant;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'participants' => 'array',
            'finalized_at' => 'datetime',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(GraduationPeriod::class, 'graduation_period_id');
    }
}
