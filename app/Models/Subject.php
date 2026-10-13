<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['tenant_id', 'name', 'code', 'credits', 'semester', 'program_id'])]
class Subject extends Model
{
    use BelongsToTenant;
}
