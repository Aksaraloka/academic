<?php

namespace App\Models;

use App\Enums\FeedbackCategory;
use App\Enums\FeedbackStatus;
use App\Enums\FeedbackType;
use App\Enums\Role;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tenant_id', 'user_id', 'handled_by', 'number', 'type', 'category', 'subject', 'body', 'is_anonymous', 'attachment_path', 'status', 'resolved_at'])]
class FeedbackTicket extends Model
{
    use BelongsToTenant;

    /**
     * Peran yang menangani masukan dan komplain.
     *
     * @return array<int, Role>
     */
    public static function handlerRoles(): array
    {
        return [Role::Admin, Role::Principal];
    }

    public static function isHandler(User $user): bool
    {
        return $user->hasRole(...self::handlerRoles());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => FeedbackType::class,
            'category' => FeedbackCategory::class,
            'status' => FeedbackStatus::class,
            'is_anonymous' => 'boolean',
            'resolved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(FeedbackReply::class)->oldest();
    }

    /**
     * Pengirim hanya melihat tiketnya sendiri; penangani melihat semua.
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! self::isHandler($user)) {
            $query->where('user_id', $user->id);
        }
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->user_id === $user->id;
    }

    /**
     * Nama pengirim sebagaimana terlihat oleh pengguna tertentu; anonim disembunyikan dari penangani.
     */
    public function senderLabelFor(User $viewer): string
    {
        if ($this->isOwnedBy($viewer)) {
            return 'Kamu';
        }

        if ($this->is_anonymous) {
            return 'Anonim';
        }

        return $this->user?->name ?? 'Pengguna dihapus';
    }

    public function isOpen(): bool
    {
        return $this->status !== FeedbackStatus::Closed;
    }
}
