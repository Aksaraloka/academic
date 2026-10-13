<?php

namespace App\Models;

use App\Enums\StudentStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tenant_id', 'user_id', 'name', 'nis', 'nisn', 'birth_date', 'gender', 'address', 'phone', 'photo_path', 'status', 'entry_date'])]
class Student extends Model
{
    use BelongsToTenant, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'entry_date' => 'date',
            'status' => StudentStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(StudyGroupMember::class);
    }

    public function records(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    /**
     * Rombel aktif pada tahun ajaran yang sedang berjalan.
     */
    public function currentGroup(): ?StudyGroup
    {
        return StudyGroup::query()
            ->whereHas('members', fn ($query) => $query->where('student_id', $this->id)->whereNull('left_at'))
            ->whereHas('academicTerm', fn ($query) => $query->where('is_active', true))
            ->first();
    }
}
