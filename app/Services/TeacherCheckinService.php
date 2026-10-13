<?php

namespace App\Services;

use App\Models\Teacher;
use App\Models\TeacherCheckin;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class TeacherCheckinService
{
    /**
     * Check-in dari HP; hanya berhasil di dalam radius geofence dan jam yang ditentukan (P-09).
     */
    public function checkIn(Teacher $teacher, Tenant $tenant, float $lat, float $lng, ?float $accuracy, bool $mockLocation = false): TeacherCheckin
    {
        $now = CarbonImmutable::now($tenant->timezone);
        $this->assertWithinHours($tenant, $now);
        $this->assertWithinRadius($tenant, $lat, $lng);

        $checkin = TeacherCheckin::query()
            ->where('teacher_id', $teacher->id)
            ->whereDate('date', $now->toDateString())
            ->first() ?? new TeacherCheckin(['teacher_id' => $teacher->id, 'date' => $now->toDateString()]);

        if ($checkin->check_in_at !== null) {
            throw ValidationException::withMessages(['location' => 'Kamu sudah check-in hari ini.']);
        }

        $checkin->fill([
            'tenant_id' => $tenant->id,
            'check_in_at' => $now,
            'lat' => $lat,
            'lng' => $lng,
            'accuracy' => $accuracy,
            'flagged' => $this->looksSpoofed($accuracy, $mockLocation),
        ])->save();

        return $checkin;
    }

    public function checkOut(Teacher $teacher, Tenant $tenant, float $lat, float $lng): TeacherCheckin
    {
        $now = CarbonImmutable::now($tenant->timezone);
        $this->assertWithinRadius($tenant, $lat, $lng);

        $checkin = TeacherCheckin::query()
            ->where('teacher_id', $teacher->id)
            ->whereDate('date', $now->toDateString())
            ->first();

        if ($checkin === null || $checkin->check_in_at === null) {
            throw ValidationException::withMessages(['location' => 'Kamu belum check-in hari ini.']);
        }

        if ($checkin->check_out_at !== null) {
            throw ValidationException::withMessages(['location' => 'Kamu sudah check-out hari ini.']);
        }

        $checkin->update(['check_out_at' => $now]);

        return $checkin;
    }

    /**
     * Jarak dua titik dalam meter (rumus haversine).
     */
    public function distanceInMeters(float $latA, float $lngA, float $latB, float $lngB): float
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($latB - $latA);
        $dLng = deg2rad($lngB - $lngA);

        $a = sin($dLat / 2) ** 2 + cos(deg2rad($latA)) * cos(deg2rad($latB)) * sin($dLng / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Lokasi palsu ditandai untuk ditinjau, bukan langsung ditolak (P-10).
     */
    public function looksSpoofed(?float $accuracy, bool $mockLocation): bool
    {
        return $mockLocation || $accuracy === null || $accuracy < 1.0;
    }

    private function assertWithinRadius(Tenant $tenant, float $lat, float $lng): void
    {
        $distance = $this->distanceInMeters(
            (float) $tenant->setting('geofence_lat'),
            (float) $tenant->setting('geofence_lng'),
            $lat,
            $lng,
        );

        if ($distance > (float) $tenant->setting('geofence_radius')) {
            throw ValidationException::withMessages([
                'location' => 'Kamu berada di luar area kampus ('.round($distance).' m dari titik pusat).',
            ]);
        }
    }

    private function assertWithinHours(Tenant $tenant, CarbonImmutable $now): void
    {
        $opens = $now->setTimeFromTimeString($tenant->setting('checkin_opens'));
        $closes = $now->setTimeFromTimeString($tenant->setting('checkin_closes'));

        if ($now->lt($opens) || $now->gt($closes)) {
            throw ValidationException::withMessages([
                'location' => "Check-in hanya dibuka pukul {$tenant->setting('checkin_opens')}–{$tenant->setting('checkin_closes')}.",
            ]);
        }
    }
}
