<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Services\IntegrationEvents;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tenant_id', 'name', 'semester', 'starts_at', 'ends_at', 'is_active', 'is_locked', 'grading_closes_on'])]
class AcademicTerm extends Model
{
    use BelongsToTenant, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
            'is_active' => 'boolean',
            'is_locked' => 'boolean',
            'grading_closes_on' => 'date',
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(AcademicEvent::class)->orderBy('starts_on');
    }

    public function studyGroups(): HasMany
    {
        return $this->hasMany(StudyGroup::class);
    }

    /**
     * Hanya satu tahun ajaran aktif per tenant (A-06).
     */
    public function activate(): void
    {
        $deactivated = static::query()->where('tenant_id', $this->tenant_id)->where('is_active', true)->whereKeyNot($this->id)->pluck('id');

        static::query()->where('tenant_id', $this->tenant_id)->update(['is_active' => false]);
        $this->update(['is_active' => true]);

        // Pembaruan massal di atas tidak memicu event model; beri tahu LMS bahwa semester lama tidak lagi aktif.
        $deactivated->each(fn (int $termId) => app(IntegrationEvents::class)->publish('term.updated', ['term_id' => $termId], $this->tenant_id));
    }

    public static function current(): ?self
    {
        return static::query()->where('is_active', true)->first();
    }
}
