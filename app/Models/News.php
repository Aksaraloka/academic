<?php

namespace App\Models;

use App\Enums\NewsCategory;
use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'graduation_period_id', 'created_by', 'category', 'title', 'summary', 'body', 'poster_path', 'is_published', 'published_at'])]
class News extends Model
{
    use BelongsToTenant;

    protected $table = 'news';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => NewsCategory::class,
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
     * Semua pengguna melihat berita yang sudah terbit; draf hanya untuk admin.
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->isAdmin()) {
            $query->published();
        }
    }

    public function hasPoster(): bool
    {
        return $this->poster_path !== null;
    }

    public function publishedDate(): CarbonInterface
    {
        return $this->published_at ?? $this->created_at;
    }
}
