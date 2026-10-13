<?php

namespace App\Livewire\Schedule;

use App\Enums\Role;
use App\Models\AcademicTerm;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\StudyGroup;
use App\Models\StudyGroupMember;
use App\Models\Teacher;
use App\Models\TimeSlot;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Jadwal')]
#[Layout('layouts.app', ['fullWidth' => true])]
class Index extends Component
{
    #[Url(as: 'semester')]
    public string $termId = '';

    #[Url(as: 'lihat')]
    public string $view = 'group';

    #[Url(as: 'id')]
    public string $targetId = '';

    public function mount(): void
    {
        $terms = $this->terms();

        if ($this->termId === '' || ! $terms->has((int) $this->termId)) {
            $this->termId = (string) ($terms->first(fn (AcademicTerm $term): bool => $term->is_active)?->id ?? $terms->first()?->id ?? '');
        }

        if ($this->targetId === '') {
            $this->resolveDefaultTarget();
        }
    }

    public function updatedTermId(): void
    {
        $this->targetId = '';
        $this->resolveDefaultTarget();
    }

    public function updatedView(): void
    {
        $this->targetId = '';
    }

    /**
     * Semua semester milik kampus, terbaru lebih dulu, berkunci id.
     *
     * @return Collection<int, AcademicTerm>
     */
    private function terms(): Collection
    {
        return AcademicTerm::query()->orderByDesc('starts_at')->get()->keyBy('id');
    }

    /**
     * Pilihan awal menurut peran: dosen melihat jadwalnya, mahasiswa dan orang tua melihat kelas anaknya
     * pada semester terpilih (kelas berbeda tiap semester), staf melihat kelas pertama.
     */
    private function resolveDefaultTarget(): void
    {
        $user = auth()->user();
        $termId = (int) $this->termId;

        if ($user->hasRole(Role::Teacher, Role::Duty) && $user->teacher) {
            $this->view = 'teacher';
            $this->targetId = (string) $user->teacher->id;

            return;
        }

        $student = $user->hasRole(Role::Student) ? $user->student : ($user->hasRole(Role::Guardian) ? $user->guardian?->students()->first() : null);

        if ($student) {
            $this->view = 'group';
            $this->targetId = (string) (StudyGroupMember::query()
                ->where('student_id', $student->id)
                ->whereHas('studyGroup', fn ($query) => $query->where('academic_term_id', $termId))
                ->latest('joined_at')
                ->value('study_group_id') ?? '');

            return;
        }

        if ($this->view === 'group') {
            $this->targetId = (string) (StudyGroup::query()->where('academic_term_id', $termId)->orderBy('name')->value('id') ?? '');
        }
    }

    public function render()
    {
        $user = auth()->user();
        $terms = $this->terms();
        $term = $terms->get((int) $this->termId);
        $canPickTarget = $user->role->isStaff();

        $options = match ($this->view) {
            'teacher' => Teacher::query()->orderBy('name')->pluck('name', 'id'),
            'room' => Room::query()->orderBy('name')->pluck('name', 'id'),
            default => StudyGroup::query()->where('academic_term_id', $term?->id)->orderBy('name')->pluck('name', 'id'),
        };

        $schedules = collect();

        if ($term && $this->targetId !== '') {
            $schedules = Schedule::query()
                ->with(['assignment.teacher', 'assignment.subject', 'assignment.studyGroup', 'timeSlot', 'room'])
                ->whereHas('assignment', fn ($query) => $query->where('academic_term_id', $term->id))
                ->when($this->view === 'teacher', fn ($query) => $query->whereHas('assignment', fn ($inner) => $inner->where('teacher_id', $this->targetId)))
                ->when($this->view === 'room', fn ($query) => $query->where('room_id', $this->targetId))
                ->when($this->view === 'group', fn ($query) => $query->whereHas('assignment', fn ($inner) => $inner->where('study_group_id', $this->targetId)))
                ->get();
        }

        $slots = TimeSlot::query()->where('is_break', false)->orderBy('day')->orderBy('period_number')->get();
        $periods = $slots->groupBy('period_number')->map(fn (Collection $group): TimeSlot => $group->sortBy('day')->first())->sortKeys();

        // Hari kuliah Senin sampai Sabtu selalu ditampilkan.
        $days = collect(TimeSlot::DAYS);

        $grid = $schedules->groupBy(fn (Schedule $schedule): string => $schedule->timeSlot->period_number.'-'.$schedule->timeSlot->day);
        $byDay = $schedules->sortBy(fn (Schedule $schedule): string => $schedule->timeSlot->starts_at)->groupBy(fn (Schedule $schedule): int => $schedule->timeSlot->day);

        $minutes = $schedules->sum(fn (Schedule $schedule): int => Carbon::parse($schedule->timeSlot->starts_at)->diffInMinutes(Carbon::parse($schedule->timeSlot->ends_at)));

        $todayDay = now()->dayOfWeekIso;
        $initialDay = $days->has($todayDay) ? $todayDay : ($byDay->keys()->sort()->first() ?? $days->keys()->first() ?? 1);

        return view('livewire.schedule.index', [
            'terms' => $terms,
            'term' => $term,
            'termIsActive' => (bool) $term?->is_active,
            'options' => $options,
            'canPickTarget' => $canPickTarget,
            'canManage' => $user->hasRole(Role::Admin, Role::Curriculum),
            'days' => $days,
            'periods' => $periods,
            'grid' => $grid,
            'byDay' => $byDay,
            'subjects' => $schedules->map(fn (Schedule $schedule) => $schedule->assignment)->unique('subject_id')->sortBy(fn ($assignment) => $assignment->subject->name)->values(),
            'stats' => [
                'meetings' => $schedules->count(),
                'courses' => $schedules->pluck('assignment.subject_id')->unique()->count(),
                'minutes' => $minutes,
            ],
            'todayDay' => $todayDay,
            'initialDay' => $initialDay,
            'nowTime' => now()->format('H:i:s'),
        ]);
    }
}
