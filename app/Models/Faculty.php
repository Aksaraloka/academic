<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tenant_id', 'name'])]
class Faculty extends Model
{
    use BelongsToTenant;

    public function programs(): HasMany
    {
        return $this->hasMany(Program::class);
    }
}
