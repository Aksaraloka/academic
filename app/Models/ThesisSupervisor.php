<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['thesis_id', 'teacher_id', 'role'])]
class ThesisSupervisor extends Model
{
    public function thesis(): BelongsTo
    {
        return $this->belongsTo(Thesis::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function roleLabel(): string
    {
        return $this->role === 'primary' ? 'Pembimbing utama' : 'Pembimbing pendamping';
    }
}
