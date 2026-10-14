<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'academic_term_id', 'survey_id', 'opens_on', 'closes_on', 'gates_grades', 'is_published'])]
class EvaluationPeriod extends Model
{
    use BelongsToTenant;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['opens_on' => 'date', 'closes_on' => 'date', 'gates_grades' => 'boolean', 'is_published' => 'boolean'];
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function isOpen(): bool
    {
        return $this->is_published && today()->between($this->opens_on, $this->closes_on);
    }

    public function phaseLabel(): string
    {
        return match (true) {
            ! $this->is_published => 'Draf',
            today()->lt($this->opens_on) => 'Akan dibuka',
            today()->gt($this->closes_on) => 'Ditutup',
            default => 'Dibuka',
        };
    }

    public function phaseVariant(): string
    {
        return match ($this->phaseLabel()) {
            'Dibuka' => 'success',
            'Akan dibuka' => 'info',
            'Ditutup' => 'neutral',
            default => 'warning',
        };
    }
}
