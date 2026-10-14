<?php

namespace App\Models;

use App\Enums\ActivityCategory;
use App\Enums\ActivityLevel;
use App\Enums\ActivityStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'student_id', 'category', 'title', 'organizer', 'role_or_result', 'level', 'started_on', 'ended_on', 'description', 'evidence_path', 'status', 'points', 'review_note', 'reviewed_by', 'reviewed_at'])]
class StudentActivity extends Model
{
    use BelongsToTenant;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => ActivityCategory::class,
            'level' => ActivityLevel::class,
            'status' => ActivityStatus::class,
            'started_on' => 'date',
            'ended_on' => 'date',
            'points' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Periode kegiatan sebagai teks, mis. "Agu 2025 – Mei 2026" atau "12 Mar 2026".
     */
    public function periodLabel(): string
    {
        if ($this->ended_on === null || $this->ended_on->isSameDay($this->started_on)) {
            return $this->started_on->translatedFormat('j M Y');
        }

        return $this->started_on->translatedFormat('M Y').' – '.$this->ended_on->translatedFormat('M Y');
    }
}
