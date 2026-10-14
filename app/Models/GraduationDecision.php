<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'yudisium_id', 'student_id', 'credits', 'gpa', 'predicate', 'checks', 'decided_by'])]
class GraduationDecision extends Model
{
    use BelongsToTenant;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['gpa' => 'decimal:2', 'checks' => 'array'];
    }

    public function yudisium(): BelongsTo
    {
        return $this->belongsTo(Yudisium::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
