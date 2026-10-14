<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['enrollment_id', 'teaching_assignment_id'])]
class EnrollmentItem extends Model
{
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(TeachingAssignment::class, 'teaching_assignment_id');
    }
}
