<?php

namespace App\Models;

use App\Enums\AttendanceMode;
use App\Enums\TenantLevel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'subdomain', 'level', 'timezone', 'settings'])]
class Tenant extends Model
{
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    public const DEFAULT_SETTINGS = [
        'attendance_mode' => 'per_hour',
        'geofence_lat' => -6.2,
        'geofence_lng' => 106.816666,
        'geofence_radius' => 100,
        'checkin_opens' => '05:30',
        'checkin_closes' => '16:00',
        'absent_threshold' => 3,
        'min_attendance_percent' => 75,
        'meetings_per_course' => 16,
        'helpdesk_whatsapp' => null,
        'grade_weights' => ['assignment' => 30, 'midterm' => 25, 'final' => 35, 'attendance' => 10],
        'grade_scale' => ['A' => 80, 'B' => 70, 'C' => 60, 'D' => 50],
        'remedial_letters' => ['D', 'E'],
        'graduation_min_credits' => 144,
        'graduation_min_gpa' => 2.0,
        'krs_requires_payment' => true,
        'thesis_min_credits_propose' => 100,
        'thesis_min_credits_defense' => 120,
        'thesis_min_guidance' => 8,
        'thesis_required_for_graduation' => true,
        'graduation_max_d_percent' => 25,
        'predicate_very_satisfactory_gpa' => 2.76,
        'predicate_cum_laude_gpa' => 3.51,
        'predicate_cum_laude_max_years' => 4.5,
        'letter_signatory_name' => null,
        'letter_signatory_title' => 'Kepala Biro Administrasi Akademik',
        'internship_min_credits' => 80,
        'conversion_max_credits' => 20,
        'va_prefix' => '8808',
        'nim_pattern' => '{YY}{PRODI}{SEQ:4}',
        'payment_bank_name' => null,
        'payment_account_number' => null,
        'payment_account_name' => null,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => TenantLevel::class,
            'settings' => 'array',
        ];
    }

    public function setting(string $key): mixed
    {
        return $this->settings[$key] ?? self::DEFAULT_SETTINGS[$key] ?? null;
    }

    /**
     * Nomor WhatsApp helpdesk dalam format internasional tanpa tanda (mis. 6281234567890); null bila belum diatur.
     * Nomor lokal berawalan 0 atau 8 diubah ke kode negara 62.
     */
    public function helpdeskNumber(): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) ($this->setting('helpdesk_whatsapp') ?: config('services.helpdesk.whatsapp')));

        if ($digits === '') {
            return null;
        }

        $digits = match (true) {
            str_starts_with($digits, '0') => '62'.substr($digits, 1),
            str_starts_with($digits, '8') => '62'.$digits,
            default => $digits,
        };

        return strlen($digits) >= 9 && strlen($digits) <= 15 ? $digits : null;
    }

    /**
     * Tautan wa.me ke helpdesk dengan pesan awal berisi nama dan peran pengirim.
     */
    public function helpdeskUrl(User $user): ?string
    {
        $number = $this->helpdeskNumber();

        if ($number === null) {
            return null;
        }

        $identity = $user->name.' ('.$user->role->label().($user->student ? ', NIM '.$user->student->nim : '').')';
        $message = "Halo Helpdesk {$this->name}, saya {$identity}. Saya membutuhkan bantuan terkait: ";

        return 'https://wa.me/'.$number.'?text='.rawurlencode($message);
    }

    public function usesDailyAttendance(): bool
    {
        return in_array($this->setting('attendance_mode'), ['daily', 'both'], true);
    }

    public function usesPerHourAttendance(): bool
    {
        return in_array($this->setting('attendance_mode'), ['per_hour', 'both'], true);
    }

    public function defaultSessionMode(): AttendanceMode
    {
        return $this->usesPerHourAttendance() ? AttendanceMode::Manual : AttendanceMode::Daily;
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
