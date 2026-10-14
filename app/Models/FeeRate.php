<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'program_id', 'kind', 'ukt_group', 'amount'])]
class FeeRate extends Model
{
    use BelongsToTenant;

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }
}
