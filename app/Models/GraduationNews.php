<?php

namespace App\Models;

use App\Enums\Role;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'graduation_period_id', 'created_by', 'title', 'summary', 'body', 'poster_path', 'is_published', 'published_at'])]
class GraduationNews extends Model
{
    use BelongsToTenant;

    protected $table = 'graduation_news';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(GraduationPeriod::class, 'graduation_period_id');
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true)->where('published_at', '<=', now());
    }

    /**
     * Pengguna yang bukan staf wisuda hanya melihat berita yang sudah terbit.
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->hasRole(Role::Admin, Role::Principal, Role::Curriculum)) {
            $query->published();
        }
    }

    public function hasPoster(): bool
    {
        return $this->poster_path !== null;
    }
}
