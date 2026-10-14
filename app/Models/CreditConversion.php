<?php

namespace App\Models;

use App\Enums\ConversionSource;
use App\Enums\ConversionStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tenant_id', 'student_id', 'internship_id', 'source', 'description', 'status', 'review_note', 'reviewed_by', 'reviewed_at'])]
class CreditConversion extends Model
{
    use BelongsToTenant;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => ConversionSource::class,
            'status' => ConversionStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function internship(): BelongsTo
    {
        return $this->belongsTo(Internship::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CreditConversionItem::class);
    }

    public function totalCredits(): int
    {
        return (int) $this->items->sum('credits');
    }
}
