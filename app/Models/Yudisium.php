<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tenant_id', 'name', 'held_on', 'number', 'status', 'finalized_at', 'finalized_by'])]
class Yudisium extends Model
{
    use BelongsToTenant;

    protected $table = 'yudisiums';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['held_on' => 'date', 'finalized_at' => 'datetime'];
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(GraduationDecision::class);
    }

    public function isFinalized(): bool
    {
        return $this->status === 'finalized';
    }
}
