<?php

namespace App\Models;

use App\Enums\Role;
use App\Enums\SurveyCategory;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tenant_id', 'created_by', 'category', 'title', 'description', 'audience', 'opens_on', 'closes_on', 'is_anonymous', 'is_published'])]
class Survey extends Model
{
    use BelongsToTenant;

    /**
     * Peran yang mengelola dan melihat hasil survey.
     *
     * @return array<int, Role>
     */
    public static function managerRoles(): array
    {
        return [Role::Admin, Role::Principal];
    }

    public static function isManager(User $user): bool
    {
        return $user->hasRole(...self::managerRoles());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => SurveyCategory::class,
            'audience' => 'array',
            'opens_on' => 'date',
            'closes_on' => 'date',
            'is_anonymous' => 'boolean',
            'is_published' => 'boolean',
        ];
    }

    public function questions(): HasMany
    {
        return $this->hasMany(SurveyQuestion::class)->orderBy('position');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(SurveyResponse::class);
    }

    /**
     * Fase: draft, upcoming, open, atau closed.
     */
    public function phase(): string
    {
        if (! $this->is_published) {
            return 'draft';
        }

        return match (true) {
            today()->lt($this->opens_on) => 'upcoming',
            today()->gt($this->closes_on) => 'closed',
            default => 'open',
        };
    }

    public function phaseLabel(): string
    {
        return match ($this->phase()) {
            'draft' => 'Draf',
            'upcoming' => 'Akan dibuka',
            'open' => 'Dibuka',
            default => 'Ditutup',
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

    public function isOpen(): bool
    {
        return $this->phase() === 'open';
    }

    public function targetsRole(Role $role): bool
    {
        return in_array($role->value, $this->audience ?? [], true);
    }

    /**
     * Survey yang diterbitkan dan ditujukan untuk peran pengguna.
     */
    public function scopeForAudience(Builder $query, User $user): void
    {
        $query->where('is_published', true)
            ->whereJsonContains('audience', $user->role->value);
    }
}
