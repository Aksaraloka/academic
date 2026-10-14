<?php

namespace App\Livewire\Schedule;

use App\Models\AcademicTerm;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\StudyGroup;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\TimeSlot;
use App\Services\ScheduleConflictDetector;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Atur jadwal')]
class Manage extends Component
{
    public string $groupId = '';

    public string $teacherId = '';

    public string $subjectId = '';

    public string $timeSlotId = '';

    public string $roomId = '';

    public string $section = '';

    public string $capacity = '';

    public string $newSubject = '';

    public string $newSubjectCode = '';

    public string $newSubjectCredits = '3';

    public string $newSubjectSemester = '';

    public string $newRoom = '';

    /** @var array<int, string> */
    public array $conflicts = [];

    public function seedTimeSlots(): void
    {
        if (TimeSlot::query()->exists()) {
            return;
        }

        $periods = [
            [1, '07:00', '07:40'], [2, '07:40', '08:20'], [3, '08:20', '09:00'],
            [4, '09:15', '09:55'], [5, '09:55', '10:35'], [6, '10:35', '11:15'],
        ];

        foreach (array_keys(TimeSlot::DAYS) as $day) {
            foreach ($periods as [$number, $start, $end]) {
                TimeSlot::create(['day' => $day, 'period_number' => $number, 'starts_at' => $start, 'ends_at' => $end]);
            }
        }

        $this->dispatch('toast', message: 'Sesi kuliah standar dibuat.', type: 'success');
    }

    public function addSubject(): void
    {
        $data = $this->validate([
            'newSubject' => ['required', 'string', 'max:100'],
            'newSubjectCode' => ['nullable', 'string', 'max:20'],
            'newSubjectCredits' => ['required', 'integer', 'between:1,12'],
            'newSubjectSemester' => ['nullable', 'integer', 'between:1,14'],
        ], [], ['newSubject' => 'nama mata kuliah', 'newSubjectCode' => 'kode', 'newSubjectCredits' => 'SKS', 'newSubjectSemester' => 'semester']);

        Subject::create([
            'name' => $data['newSubject'],
            'code' => $data['newSubjectCode'] ?: null,
            'credits' => $data['newSubjectCredits'],
            'semester' => $data['newSubjectSemester'] ?: null,
        ]);

        $this->reset(['newSubject', 'newSubjectCode', 'newSubjectSemester']);
    }

    public function addRoom(): void
    {
        $data = $this->validate(['newRoom' => ['required', 'string', 'max:100']], [], ['newRoom' => 'nama ruang']);
        Room::create(['name' => $data['newRoom']]);
        $this->reset('newRoom');
    }

    public function place(ScheduleConflictDetector $detector): void
    {
        $this->validate([
            'groupId' => ['required', 'exists:study_groups,id'],
            'teacherId' => ['required', 'exists:teachers,id'],
            'subjectId' => ['required', 'exists:subjects,id'],
            'timeSlotId' => ['required', 'exists:time_slots,id'],
            'roomId' => ['nullable', 'exists:rooms,id'],
            'section' => ['nullable', 'string', 'max:10'],
            'capacity' => ['nullable', 'integer', 'between:1,500'],
        ], [], [
            'groupId' => 'kelas', 'teacherId' => 'dosen', 'subjectId' => 'mata kuliah',
            'timeSlotId' => 'sesi kuliah', 'roomId' => 'ruang', 'section' => 'seksi', 'capacity' => 'kuota',
        ]);

        $group = StudyGroup::findOrFail($this->groupId);

        $assignment = TeachingAssignment::query()->firstOrCreate([
            'academic_term_id' => $group->academic_term_id,
            'teacher_id' => $this->teacherId,
            'subject_id' => $this->subjectId,
            'study_group_id' => $group->id,
        ]);

        $assignment->update([
            'section' => $this->section !== '' ? $this->section : $assignment->section,
            'capacity' => $this->capacity !== '' ? (int) $this->capacity : $assignment->capacity,
        ]);

        $roomId = $this->roomId !== '' ? (int) $this->roomId : null;
        $this->conflicts = $detector->conflictsFor($assignment, (int) $this->timeSlotId, $roomId);

        if ($this->conflicts !== []) {
            return;
        }

        Schedule::create([
            'teaching_assignment_id' => $assignment->id,
            'time_slot_id' => $this->timeSlotId,
            'room_id' => $roomId,
        ]);

        $assignment->update(['hours_per_week' => $assignment->schedules()->count()]);
        $this->reset(['timeSlotId']);
        $this->dispatch('toast', message: 'Jadwal ditambahkan tanpa bentrok.', type: 'success');
    }

    public function remove(int $scheduleId): void
    {
        Schedule::query()->whereKey($scheduleId)->delete();
        $this->dispatch('toast', message: 'Jadwal dihapus.', type: 'success');
    }

    public function render()
    {
        return view('livewire.schedule.manage', [
            'term' => AcademicTerm::current(),
            'groups' => StudyGroup::query()->whereHas('academicTerm', fn ($query) => $query->where('is_active', true))->orderBy('name')->get(),
            'teachers' => Teacher::query()->orderBy('name')->get(),
            'subjects' => Subject::query()->orderBy('name')->get(),
            'rooms' => Room::query()->orderBy('name')->get(),
            'timeSlots' => TimeSlot::query()->where('is_break', false)->orderBy('day')->orderBy('period_number')->get(),
            'schedules' => $this->groupId === '' ? collect() : Schedule::query()
                ->with(['assignment.teacher', 'assignment.subject', 'timeSlot', 'room'])
                ->whereHas('assignment', fn ($query) => $query->where('study_group_id', $this->groupId))
                ->get()
                ->sortBy(fn (Schedule $schedule): string => $schedule->timeSlot->day.$schedule->timeSlot->starts_at),
        ]);
    }
}
