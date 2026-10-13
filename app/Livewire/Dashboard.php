<?php

namespace App\Livewire;

use App\Enums\Role;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Student;
use App\Models\StudyGroup;
use App\Models\TeacherCheckin;
use App\Services\AttendanceReport;
use Illuminate\Support\Collection;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Beranda')]
class Dashboard extends Component
{
    public function render(AttendanceReport $report)
    {
        $user = auth()->user();
        $today = now();
        $data = ['today' => $today];

        $data['role'] = $user->role;

        if ($user->hasRole(Role::Teacher, Role::Duty, Role::Curriculum, Role::Principal) && $user->teacher) {
            $data['mySchedules'] = $report->schedulesOn($today, teacherId: $user->teacher->id);
            $data['sessions'] = AttendanceSession::query()
                ->whereDate('date', $today)
                ->whereIn('schedule_id', $data['mySchedules']->pluck('id'))
                ->get()
                ->keyBy('schedule_id');
            $data['checkin'] = TeacherCheckin::query()
                ->where('teacher_id', $user->teacher->id)
                ->whereDate('date', $today)
                ->first();
            $data['homeroomGroups'] = StudyGroup::query()
                ->where('homeroom_teacher_id', $user->teacher->id)
                ->whereHas('academicTerm', fn ($query) => $query->where('is_active', true))
                ->get();
        }

        if ($user->hasRole(Role::Admin, Role::Principal, Role::Curriculum)) {
            $data['overview'] = $report->dayOverview($today);
            $data['notCheckedIn'] = $report->teachersNotCheckedIn($today);
            $data['studentCount'] = Student::query()->where('status', 'aktif')->count();
        }

        if ($user->hasRole(Role::Student) && $user->student) {
            $group = $user->student->currentGroup();
            $data['group'] = $group;
            $data['mySchedules'] = $group ? $report->schedulesOn($today, groupId: $group->id) : collect();
            $data['summary'] = $report->studentSummary($user->student->id);
            $data['todayRecords'] = $this->recordsToday([$user->student->id]);
        }

        if ($user->hasRole(Role::Guardian) && $user->guardian) {
            $children = $user->guardian->students()->get();
            $data['children'] = $children;
            $data['todayRecords'] = $this->recordsToday($children->pluck('id')->all());
            $data['summaries'] = $children->mapWithKeys(fn ($child) => [$child->id => $report->studentSummary($child->id)]);
        }

        return view('livewire.dashboard', $data);
    }

    /**
     * @param  array<int, int>  $studentIds
     * @return Collection<int, Collection<int, AttendanceRecord>>
     */
    private function recordsToday(array $studentIds)
    {
        return AttendanceRecord::query()
            ->with('session.schedule.assignment.subject', 'session.schedule.timeSlot')
            ->whereIn('student_id', $studentIds)
            ->whereHas('session', fn ($query) => $query->whereDate('date', now()))
            ->get()
            ->groupBy('student_id');
    }
}
