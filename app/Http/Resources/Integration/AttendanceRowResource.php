<?php

namespace App\Http\Resources\Integration;

use App\Enums\AttendanceStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Rekap kehadiran satu mahasiswa pada satu kuliah. Sumber `$this->resource`: `['student' => Student, 'recap' => array]`
 * dari `AttendanceReport::assignmentRecap`.
 */
class AttendanceRowResource extends JsonResource
{
    /** Tingkat di halaman Academic -> `standing` kontrak v1. Mahasiswa tanpa presensi ("baru") belum punya risiko. */
    private const STANDING = ['baru' => 'aman', 'aman' => 'aman', 'waspada' => 'waspada', 'risiko' => 'tidak_memenuhi'];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $recap = $this->resource['recap'];

        return [
            'user_id' => $this->resource['student']->user_id,
            'sessions' => $recap['total'],
            'present' => $recap['counts'][AttendanceStatus::Present->value] + $recap['counts'][AttendanceStatus::Late->value],
            'excused' => $recap['counts'][AttendanceStatus::Excused->value],
            'sick' => $recap['counts'][AttendanceStatus::Sick->value],
            'absent' => $recap['counts'][AttendanceStatus::Absent->value],
            'percent' => $recap['percent'],
            'standing' => self::STANDING[$recap['level']],
        ];
    }
}
