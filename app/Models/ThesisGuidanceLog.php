<?php

namespace App\Models;

use App\Enums\GuidanceStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['thesis_id', 'teacher_id', 'held_on', 'topic', 'summary', 'next_steps', 'status', 'feedback', 'verified_at'])]
class ThesisGuidanceLog extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['held_on' => 'date', 'status' => GuidanceStatus::class, 'verified_at' => 'datetime'];
    }

    public function thesis(): BelongsTo
    {
        return $this->belongsTo(Thesis::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }
}
