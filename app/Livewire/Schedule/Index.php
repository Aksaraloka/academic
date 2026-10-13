<?php

namespace App\Livewire\Schedule;

use App\Enums\Role;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\StudyGroup;
use App\Models\Teacher;
use App\Models\TimeSlot;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Jadwal')]
class Index extends Component
{
    #[Url(as: 'lihat')]
    public string $view = 'group';

    #[Url(as: 'id')]
    public string $targetId = '';

    public function mount(): void
    {
        $user = auth()->user();

        if ($this->targetId !== '') {
            return;
        }

        if ($user->hasRole(Role::Teacher, Role::Duty) && $user->teacher) {
            $this->view = 'teacher';
            $this->targetId = (string) $user->teacher->id;
        } elseif ($user->hasRole(Role::Student) && $user->student) {
            $this->targetId = (string) ($user->student->currentGroup()?->id ?? '');
        } elseif ($user->hasRole(Role::Guardian)) {
            $child = $user->guardian?->students()->first();
            $this->targetId = (string) ($child?->currentGroup()?->id ?? '');
        } else {
            $this->targetId = (string) (StudyGroup::query()->whereHas('academicTerm', fn ($query) => $query->where('is_active', true))->orderBy('name')->value('id') ?? '');
        }
    }

    public function updatedView(): void
    {
        $this->targetId = '';
    }

    public function render()
    {
        $canPickTarget = auth()->user()->role->isStaff();
        $options = match ($this->view) {
            'teacher' => Teacher::query()->orderBy('name')->pluck('name', 'id'),
            'room' => Room::query()->orderBy('name')->pluck('name', 'id'),
            default => StudyGroup::query()->whereHas('academicTerm', fn ($query) => $query->where('is_active', true))->orderBy('name')->pluck('name', 'id'),
        };

        $schedules = collect();

        if ($this->targetId !== '') {
            $schedules = Schedule::query()
                ->with(['assignment.teacher', 'assignment.subject', 'assignment.studyGroup', 'timeSlot', 'room'])
                ->whereHas('assignment.studyGroup.academicTerm', fn ($query) => $query->where('is_active', true))
                ->when($this->view === 'teacher', fn ($query) => $query->whereHas('assignment', fn ($inner) => $inner->where('teacher_id', $this->targetId)))
                ->when($this->view === 'room', fn ($query) => $query->where('room_id', $this->targetId))
                ->when($this->view === 'group', fn ($query) => $query->whereHas('assignment', fn ($inner) => $inner->where('study_group_id', $this->targetId)))
                ->get()
                ->sortBy(fn (Schedule $schedule): string => $schedule->timeSlot->starts_at)
                ->groupBy(fn (Schedule $schedule): int => $schedule->timeSlot->day);
        }

        return view('livewire.schedule.index', [
            'days' => TimeSlot::DAYS,
            'options' => $options,
            'canPickTarget' => $canPickTarget,
            'schedulesByDay' => $schedules,
        ]);
    }
}
