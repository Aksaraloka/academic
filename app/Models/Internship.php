<?php

namespace App\Models;

use App\Enums\InternshipStatus;
use App\Enums\InternshipType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'student_id', 'type', 'partner_name', 'position', 'location', 'field_supervisor', 'starts_on', 'ends_on', 'description', 'proposed_credits', 'status', 'supervisor_id', 'decision_note', 'decided_by', 'decided_at', 'final_letter', 'evaluation_note', 'completed_at'])]
class Internship extends Model
{
    use BelongsToTenant;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => InternshipType::class,
            'status' => InternshipStatus::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
            'proposed_credits' => 'integer',
            'decided_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'supervisor_id');
    }

    public function periodLabel(): string
    {
        return $this->starts_on->translatedFormat('j M Y').' – '.$this->ends_on->translatedFormat('j M Y');
    }
}
