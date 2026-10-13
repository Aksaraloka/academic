<?php

namespace App\Livewire\Attendance;

use App\Models\AcademicTerm;
use App\Models\AttendanceRecord;
use App\Services\AttendanceReport;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Presensi saya')]
class Mine extends Component
{
    public function render(AttendanceReport $report)
    {
        $user = auth()->user();
        $student = $user->student;
        $tenant = $user->tenant;
        $term = AcademicTerm::current();
        $group = $student?->currentGroup();
        $minPercent = (int) $tenant->setting('min_attendance_percent');
        $planned = (int) $tenant->setting('meetings_per_course');

        $courses = $student && $group && $term
            ? $report->studentCourseAnalytics($student, $group, $term, $minPercent, $planned)
            : collect();

        $todaySchedules = $group ? $report->schedulesOn(now(), groupId: $group->id) : collect();
        $todayRecords = $student && $todaySchedules->isNotEmpty()
            ? AttendanceRecord::query()
                ->where('student_id', $student->id)
                ->whereHas('session', fn ($query) => $query->whereDate('date', today())->whereIn('schedule_id', $todaySchedules->pluck('id')))
                ->with('session:id,schedule_id')
                ->get()
                ->keyBy(fn (AttendanceRecord $record): int => $record->session->schedule_id)
            : collect();

        $held = $courses->sum('total');
        $attended = $courses->sum('attended');

        return view('livewire.attendance.mine', [
            'student' => $student,
            'group' => $group,
            'term' => $term,
            'courses' => $courses,
            'minPercent' => $minPercent,
            'planned' => $planned,
            'statuses' => $user->attendanceStatuses(),
            'todaySchedules' => $todaySchedules,
            'todayRecords' => $todayRecords,
            'overall' => [
                'held' => $held,
                'attended' => $attended,
                'percent' => $held > 0 ? (int) round($attended / $held * 100) : null,
                'absent' => $courses->sum(fn (array $course): int => $course['counts']['alpa']),
                'atRisk' => $courses->whereIn('level', ['risiko', 'waspada'])->count(),
            ],
        ]);
    }
}
