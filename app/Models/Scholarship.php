<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tenant_id', 'name', 'provider', 'description', 'requirements', 'amount_per_term', 'quota', 'min_gpa', 'min_semester', 'opens_on', 'closes_on', 'is_active'])]
class Scholarship extends Model
{
    use BelongsToTenant;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opens_on' => 'date',
            'closes_on' => 'date',
            'is_active' => 'boolean',
            'min_gpa' => 'decimal:2',
            'amount_per_term' => 'integer',
            'quota' => 'integer',
            'min_semester' => 'integer',
        ];
    }

    public function applications(): HasMany
    {
        return $this->hasMany(ScholarshipApplication::class);
    }

    public function isOpen(): bool
    {
        return $this->is_active && today()->betweenIncluded($this->opens_on, $this->closes_on);
    }

    public function acceptedCount(): int
    {
        return $this->applications()->where('status', ApplicationStatus::Accepted)->count();
    }
}
