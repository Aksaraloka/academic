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

#[Fillable(['tenant_id', 'user_id', 'program_id', 'name', 'nim', 'batch_year', 'birth_date', 'birth_place', 'gender', 'religion', 'nik', 'nationality', 'address', 'city', 'province', 'postal_code', 'phone', 'photo_path', 'status', 'entry_date', 'previous_school', 'admission_path', 'ukt_group', 'is_domicile_same', 'residence_type', 'domicile_address', 'domicile_city', 'domicile_province', 'domicile_postal_code', 'legacy_credits', 'legacy_quality_points'])]
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
     * @var array<int, string>
     */
    public const RESIDENCE_TYPES = ['Bersama orang tua', 'Kos/kontrakan', 'Asrama', 'Rumah saudara/wali', 'Lainnya'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'is_domicile_same' => 'boolean',
            'legacy_credits' => 'integer',
            'legacy_quality_points' => 'decimal:2',
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

    /**
     * Alamat domisili yang berlaku: sama dengan KTP bila ditandai sama atau belum diisi.
     *
     * @return array{address: ?string, city: ?string, province: ?string, postal_code: ?string}
     */
    public function currentAddress(): array
    {
        if ($this->is_domicile_same || blank($this->domicile_address)) {
            return ['address' => $this->address, 'city' => $this->city, 'province' => $this->province, 'postal_code' => $this->postal_code];
        }

        return ['address' => $this->domicile_address, 'city' => $this->domicile_city, 'province' => $this->domicile_province, 'postal_code' => $this->domicile_postal_code];
    }

    /**
     * Perantau: tinggal di kota yang berbeda dari kota pada KTP.
     */
    public function isMigrant(): bool
    {
        return ! $this->is_domicile_same
            && filled($this->domicile_city)
            && filled($this->city)
            && mb_strtolower(trim($this->domicile_city)) !== mb_strtolower(trim($this->city));
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

    public function advisingNotes(): HasMany
    {
        return $this->hasMany(AdvisingNote::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function termStatuses(): HasMany
    {
        return $this->hasMany(StudentTermStatus::class);
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
