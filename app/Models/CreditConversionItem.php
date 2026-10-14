<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['credit_conversion_id', 'subject_id', 'credits', 'letter', 'points'])]
class CreditConversionItem extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['credits' => 'integer', 'points' => 'decimal:1'];
    }

    public function conversion(): BelongsTo
    {
        return $this->belongsTo(CreditConversion::class, 'credit_conversion_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}
