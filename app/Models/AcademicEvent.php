<?php

namespace App\Models;

use App\Enums\AcademicEventType;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\AcademicEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'academic_term_id', 'type', 'title', 'starts_on', 'ends_on', 'notes'])]
class AcademicEvent extends Model
{
    /** @use HasFactory<AcademicEventFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AcademicEventType::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function displayTitle(): string
    {
        return $this->title ?: $this->type->label();
    }

    public function isOn(\DateTimeInterface $date): bool
    {
        return $this->starts_on->copy()->startOfDay()->lte($date) && $this->ends_on->copy()->endOfDay()->gte($date);
    }
}
