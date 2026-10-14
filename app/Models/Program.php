<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tenant_id', 'faculty_id', 'name', 'code', 'degree'])]
class Program extends Model
{
    use BelongsToTenant;

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    public function feeRates(): HasMany
    {
        return $this->hasMany(FeeRate::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function label(): string
    {
        return trim("{$this->degree} {$this->name}");
    }
}
