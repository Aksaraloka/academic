<?php

namespace App\Services;

use App\Models\Schedule;
use App\Models\TeachingAssignment;

class ScheduleConflictDetector
{
    /**
     * Cari bentrok dosen, kelas, dan ruang untuk penempatan jadwal (A-15).
     *
     * @return array<int, string> daftar pesan bentrok; kosong berarti aman
     */
    public function conflictsFor(TeachingAssignment $assignment, int $timeSlotId, ?int $roomId, ?int $ignoreScheduleId = null): array
    {
        $conflicts = [];

        $sameSlot = Schedule::query()
            ->with(['assignment.teacher', 'assignment.studyGroup', 'assignment.subject', 'room'])
            ->where('time_slot_id', $timeSlotId)
            ->whereHas('assignment', fn ($query) => $query->where('academic_term_id', $assignment->academic_term_id))
            ->when($ignoreScheduleId, fn ($query) => $query->whereKeyNot($ignoreScheduleId))
            ->get();

        foreach ($sameSlot as $existing) {
            $other = $existing->assignment;

            if ($other->teacher_id === $assignment->teacher_id) {
                $conflicts[] = "{$other->teacher->name} sudah mengajar {$other->subject->name} di {$other->studyGroup->name} pada jam ini.";
            }

            if ($other->study_group_id === $assignment->study_group_id) {
                $conflicts[] = "Kelas {$other->studyGroup->name} sudah punya kuliah {$other->subject->name} pada jam ini.";
            }

            if ($roomId !== null && $existing->room_id === $roomId) {
                $conflicts[] = "Ruang {$existing->room->name} sudah dipakai {$other->studyGroup->name} pada jam ini.";
            }
        }

        return array_values(array_unique($conflicts));
    }
}
