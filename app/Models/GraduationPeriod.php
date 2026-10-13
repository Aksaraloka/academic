<?php

namespace App\Models;

use App\Enums\GraduationStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['tenant_id', 'name', 'registration_opens_on', 'registration_closes_on', 'ceremony_on', 'venue', 'quota', 'is_published'])]
class GraduationPeriod extends Model
{
    use BelongsToTenant;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'registration_opens_on' => 'date',
            'registration_closes_on' => 'date',
            'ceremony_on' => 'date',
            'is_published' => 'boolean',
        ];
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(GraduationRegistration::class);
    }

    public function minute(): HasOne
    {
        return $this->hasOne(GraduationMinute::class);
    }

    /**
     * Fase periode: draft (belum diterbitkan), upcoming, open, atau closed.
     */
    public function phase(): string
    {
        if (! $this->is_published) {
            return 'draft';
        }

        $today = today();

        return match (true) {
            $today->lt($this->registration_opens_on) => 'upcoming',
            $today->gt($this->registration_closes_on) => 'closed',
            default => 'open',
        };
    }

    public function isOpen(): bool
    {
        return $this->phase() === 'open';
    }

    public function phaseLabel(): string
    {
        return match ($this->phase()) {
            'draft' => 'Draf',
            'upcoming' => 'Akan dibuka',
            'open' => 'Pendaftaran dibuka',
            default => 'Pendaftaran ditutup',
        };
    }

    public function phaseVariant(): string
    {
        return match ($this->phase()) {
            'draft' => 'warning',
            'upcoming' => 'info',
            'open' => 'success',
            default => 'neutral',
        };
    }

    public function takenSeats(): int
    {
        return $this->registrations()->whereIn('status', [GraduationStatus::Submitted, GraduationStatus::Approved])->count();
    }

    public function isFull(): bool
    {
        return $this->quota !== null && $this->takenSeats() >= $this->quota;
    }

    public function isFinalized(): bool
    {
        return $this->minute?->finalized_at !== null;
    }
}
