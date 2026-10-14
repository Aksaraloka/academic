<?php

namespace App\Livewire\Schedule;

use App\Enums\Role;
use App\Models\Room;
use App\Models\StudyGroup;
use App\Models\Teacher;
use App\Services\ScheduleBoard;
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

    #[Url(as: 'view')]
    public string $view = 'group';

    #[Url(as: 'id')]
    public string $targetId = '';

    public function mount(ScheduleBoard $board): void
    {
        $this->termId = (string) ($board->resolveTerm($board->terms(), $this->termId)?->id ?? '');

        if ($this->targetId === '') {
            $this->resolveDefaultTarget($board);
        }
    }

    public function updatedTermId(ScheduleBoard $board): void
    {
        $this->targetId = '';
        $this->resolveDefaultTarget($board);
    }

    public function updatedView(): void
    {
        $this->targetId = '';
    }

    private function resolveDefaultTarget(ScheduleBoard $board): void
    {
        $default = $board->defaultTarget(auth()->user(), (int) $this->termId, $this->view);

        $this->view = $default['view'];
        $this->targetId = $default['id'];
    }

    public function render(ScheduleBoard $board)
    {
        $user = auth()->user();
        $terms = $board->terms();
        $term = $terms->get((int) $this->termId);

        $options = match ($this->view) {
            'teacher' => Teacher::query()->orderBy('name')->pluck('name', 'id'),
            'room' => Room::query()->orderBy('name')->pluck('name', 'id'),
            default => StudyGroup::query()->where('academic_term_id', $term?->id)->orderBy('name')->pluck('name', 'id'),
        };

        $data = $board->build($term, $this->view, $this->targetId);
        $todayDay = now()->dayOfWeekIso;

        return view('livewire.schedule.index', $data + [
            'terms' => $terms,
            'term' => $term,
            'termIsActive' => (bool) $term?->is_active,
            'options' => $options,
            'canPickTarget' => $user->role->isStaff(),
            'canManage' => $user->hasRole(Role::Admin, Role::Curriculum),
            'todayDay' => $todayDay,
            'initialDay' => $data['days']->has($todayDay) ? $todayDay : ($data['byDay']->keys()->sort()->first() ?? 1),
            'nowTime' => now()->format('H:i:s'),
            'exportQuery' => array_filter(['semester' => $this->termId, 'view' => $this->view, 'id' => $this->targetId], fn ($value) => $value !== ''),
        ]);
    }
}
