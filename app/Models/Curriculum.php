<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\CurriculumFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tenant_id', 'program_id', 'name', 'batch_from', 'batch_to', 'is_active'])]
class Curriculum extends Model
{
    /** @use HasFactory<CurriculumFactory> */
    use BelongsToTenant, HasFactory;

    protected $table = 'curricula';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CurriculumSubject::class);
    }

    public function appliesToBatch(int $batch): bool
    {
        return $this->is_active && $batch >= $this->batch_from && ($this->batch_to === null || $batch <= $this->batch_to);
    }

    public function batchLabel(): string
    {
        return $this->batch_to === null ? "Angkatan {$this->batch_from} dan seterusnya" : "Angkatan {$this->batch_from}–{$this->batch_to}";
    }
}
