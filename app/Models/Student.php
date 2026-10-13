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

#[Fillable(['tenant_id', 'user_id', 'program_id', 'name', 'nim', 'batch_year', 'birth_date', 'birth_place', 'gender', 'religion', 'nik', 'nationality', 'address', 'city', 'province', 'postal_code', 'phone', 'photo_path', 'status', 'entry_date', 'previous_school', 'admission_path'])]
class Student extends Model
{
    use BelongsToTenant, HasFactory;

    /**
     * @var array<int, string>
     */
    public const RELIGIONS = ['Islam', 'Kristen Protestan', 'Katolik', 'Hindu', 'Buddha', 'Konghucu', 'Lainnya'];

    /**
     * @var array<int, string>
     */
    public const ADMISSION_PATHS = ['SNBP', 'SNBT', 'Mandiri', 'Beasiswa', 'Pindahan'];

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

    /**
     * NIK ditampilkan tersamar (4 digit awal dan 4 digit akhir) agar aman dilihat di layar.
     */
    public function maskedNik(): ?string
    {
        if ($this->nik === null) {
            return null;
        }

        return substr($this->nik, 0, 4).str_repeat('•', max(strlen($this->nik) - 8, 0)).substr($this->nik, -4);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
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
     * Kelas aktif pada tahun ajaran yang sedang berjalan.
     */
    public function currentGroup(): ?StudyGroup
    {
        return StudyGroup::query()
            ->whereHas('members', fn ($query) => $query->where('student_id', $this->id)->whereNull('left_at'))
            ->whereHas('academicTerm', fn ($query) => $query->where('is_active', true))
            ->first();
    }
}
