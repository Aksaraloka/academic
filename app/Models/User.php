<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Enums\Role;
use App\Enums\TenantLevel;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['tenant_id', 'name', 'email', 'phone', 'role', 'theme', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function teacher(): HasOne
    {
        return $this->hasOne(Teacher::class);
    }

    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    public function guardian(): HasOne
    {
        return $this->hasOne(Guardian::class);
    }

    public function hasRole(Role ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    /**
     * Status kehadiran yang dipakai institusi pengguna (kampus tidak memakai Terlambat).
     *
     * @return array<int, AttendanceStatus>
     */
    public function attendanceStatuses(): array
    {
        return AttendanceStatus::forLevel($this->tenant?->level ?? TenantLevel::Campus);
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    /**
     * Dosen wali: dosen yang menjadi wali minimal satu kelas pada tahun ajaran aktif.
     */
    public function isHomeroomTeacher(): bool
    {
        return $this->teacher !== null
            && StudyGroup::query()
                ->where('homeroom_teacher_id', $this->teacher->id)
                ->whereHas('academicTerm', fn ($query) => $query->where('is_active', true))
                ->exists();
    }

    public function initials(): string
    {
        return collect(explode(' ', trim($this->name)))
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }
}
