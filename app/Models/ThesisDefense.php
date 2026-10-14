<?php

namespace App\Models;

use App\Enums\DefenseResult;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['thesis_id', 'scheduled_on', 'starts_at', 'ends_at', 'room_id', 'chair_id', 'examiner_id', 'status', 'result', 'score', 'notes', 'revision_deadline', 'scheduled_by'])]
class ThesisDefense extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['scheduled_on' => 'date', 'revision_deadline' => 'date', 'result' => DefenseResult::class, 'score' => 'decimal:2'];
    }

    public function thesis(): BelongsTo
    {
        return $this->belongsTo(Thesis::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function chair(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'chair_id');
    }

    public function examiner(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'examiner_id');
    }

    public function timeRange(): string
    {
        return substr($this->starts_at, 0, 5).'–'.substr($this->ends_at, 0, 5);
    }
}
