<?php

namespace App\Services;

use App\Models\AttendanceSession;
use App\Models\ClassJournal;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use Illuminate\Support\Collection;

/**
 * BAP / jurnal perkuliahan: materi yang diajarkan tiap pertemuan, diisi dosen bersama presensi.
 */
class JournalService
{
    /**
     * Nomor pertemuan berikutnya untuk kuliah itu, berurutan menurut tanggal sesi presensi.
     */
    public function meetingNumber(AttendanceSession $session): int
    {
        $assignmentId = $session->schedule?->teaching_assignment_id;

        return AttendanceSession::query()
            ->whereHas('schedule', fn ($query) => $query->where('teaching_assignment_id', $assignmentId))
            ->where(fn ($query) => $query->whereDate('date', '<', $session->date->toDateString())
                ->orWhere(fn ($same) => $same->whereDate('date', $session->date->toDateString())->where('id', '<=', $session->id)))
            ->count();
    }

    /**
     * @param  array{topic: string, material?: ?string, notes?: ?string}  $data
     */
    public function save(AttendanceSession $session, Teacher $teacher, array $data): ClassJournal
    {
        abort_if($session->schedule === null, 422, 'Jurnal hanya untuk sesi kuliah.');

        return ClassJournal::query()->updateOrCreate(
            ['attendance_session_id' => $session->id],
            [
                'tenant_id' => $session->tenant_id, 'teaching_assignment_id' => $session->schedule->teaching_assignment_id, 'teacher_id' => $teacher->id,
                'meeting_number' => $this->meetingNumber($session), 'topic' => trim($data['topic']),
                'material' => filled($data['material'] ?? null) ? trim((string) $data['material']) : null,
                'notes' => filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null,
            ],
        );
    }

    /**
     * Seluruh pertemuan kuliah yang sudah dipresensi, dengan jurnalnya bila sudah diisi.
     *
     * @return Collection<int, array{session: AttendanceSession, journal: ?ClassJournal, number: int, present: int, total: int}>
     */
    public function meetings(TeachingAssignment $assignment): Collection
    {
        $sessions = AttendanceSession::query()
            ->withCount([
                'records as total_count',
                'records as present_count' => fn ($query) => $query->whereIn('status', ['hadir', 'terlambat']),
            ])
            ->whereHas('schedule', fn ($query) => $query->where('teaching_assignment_id', $assignment->id))
            ->orderBy('date')->orderBy('id')
            ->get();

        $journals = ClassJournal::query()->whereIn('attendance_session_id', $sessions->pluck('id'))->get()->keyBy('attendance_session_id');

        return $sessions->values()->map(fn (AttendanceSession $session, int $index): array => [
            'session' => $session, 'journal' => $journals->get($session->id), 'number' => $index + 1,
            'present' => (int) $session->present_count, 'total' => (int) $session->total_count,
        ]);
    }
}
