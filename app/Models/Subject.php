<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['tenant_id', 'name', 'code', 'credits', 'semester', 'program_id'])]
class Subject extends Model
{
    use BelongsToTenant;

    /**
     * Mata kuliah yang harus lulus lebih dulu.
     */
    public function prerequisites(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'subject_prerequisites', 'subject_id', 'prerequisite_id')->withTimestamps();
    }
}
