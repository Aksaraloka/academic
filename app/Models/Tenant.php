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
        'graduation_min_credits' => 144,
        'graduation_min_gpa' => 2.0,
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
