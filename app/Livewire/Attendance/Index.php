<?php

namespace App\Livewire\Attendance;

use App\Enums\AttendanceMode;
use App\Enums\Role;
use App\Models\AttendanceSession;
use App\Models\Schedule;
use App\Models\StudyGroup;
use App\Services\AttendanceReport;
use App\Services\AttendanceService;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Presensi')]
class Index extends Component
{
    public function open(int $scheduleId, string $mode, AttendanceService $service): mixed
    {
        $user = auth()->user();
        $schedule = Schedule::query()->with('assignment.studyGroup')->findOrFail($scheduleId);

        $ownsSchedule = $user->teacher && $schedule->assignment->teacher_id === $user->teacher->id;
        abort_unless($user->isAdmin() || $ownsSchedule, 403);

        $session = $service->openSession($schedule, $user, AttendanceMode::from($mode));

        return $this->redirectRoute($session->mode === AttendanceMode::Qr ? 'attendance.qr' : 'attendance.mark', $session, navigate: true);
    }

    public function openDaily(int $groupId, AttendanceService $service): mixed
    {
        $user = auth()->user();
        $group = StudyGroup::query()->findOrFail($groupId);

        $isHomeroom = $user->teacher && $group->homeroom_teacher_id === $user->teacher->id;
        abort_unless($user->hasRole(Role::Admin, Role::Duty) || $isHomeroom, 403);

        $session = $service->openDailySession($group, $user);

        return $this->redirectRoute('attendance.mark', $session, navigate: true);
    }

    public function render(AttendanceReport $report)
    {
        $user = auth()->user();
        $today = now();
        $seesAll = $user->hasRole(Role::Admin, Role::Principal);
        $tenant = $user->tenant;

        $schedules = $report->schedulesOn($today, teacherId: $seesAll || ! $user->teacher ? null : $user->teacher->id);
        $sessions = AttendanceSession::query()
            ->withCount(['records', 'records as present_count' => fn ($query) => $query->whereIn('status', ['hadir', 'terlambat'])])
            ->whereDate('date', $today)
            ->get();

        $dailyGroups = collect();

        if ($tenant->usesDailyAttendance()) {
            $dailyGroups = StudyGroup::query()
                ->whereHas('academicTerm', fn ($query) => $query->where('is_active', true))
                ->when(! $user->hasRole(Role::Admin, Role::Duty, Role::Principal), fn ($query) => $query->where('homeroom_teacher_id', $user->teacher?->id))
                ->orderBy('name')
                ->get();
        }

        return view('livewire.attendance.index', [
            'schedules' => $schedules,
            'sessionsBySchedule' => $sessions->whereNotNull('schedule_id')->keyBy('schedule_id'),
            'dailySessions' => $sessions->whereNull('schedule_id')->keyBy('study_group_id'),
            'dailyGroups' => $dailyGroups,
            'canOpen' => ! $user->hasRole(Role::Principal),
            'tenant' => $tenant,
        ]);
    }
}
