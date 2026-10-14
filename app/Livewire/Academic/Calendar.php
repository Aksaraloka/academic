<?php

namespace App\Livewire\Academic;

use App\Enums\AcademicEventType;
use App\Enums\Role;
use App\Models\AcademicEvent;
use App\Models\AcademicTerm;
use App\Services\AcademicCalendar;
use App\Services\AuditLogger;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Kalender akademik')]
class Calendar extends Component
{
    #[Url(as: 'term')]
    public string $termId = '';

    public ?int $editingId = null;

    public string $type = 'midterm';

    public string $title = '';

    public string $starts_on = '';

    public string $ends_on = '';

    public string $notes = '';

    private function canManage(): bool
    {
        return in_array(auth()->user()->role, [Role::Admin, Role::Curriculum], true);
    }

    private function term(): ?AcademicTerm
    {
        return ($this->termId !== '' ? AcademicTerm::query()->find($this->termId) : null)
            ?? AcademicTerm::current()
            ?? AcademicTerm::query()->orderByDesc('starts_at')->first();
    }

    public function edit(int $eventId): void
    {
        abort_unless($this->canManage(), 403);

        $event = AcademicEvent::query()->findOrFail($eventId);

        $this->editingId = $event->id;
        $this->type = $event->type->value;
        $this->title = (string) $event->title;
        $this->starts_on = $event->starts_on->toDateString();
        $this->ends_on = $event->ends_on->toDateString();
        $this->notes = (string) $event->notes;
        $this->resetErrorBag();
    }

    public function cancel(): void
    {
        $this->reset(['editingId', 'title', 'starts_on', 'ends_on', 'notes']);
        $this->type = 'midterm';
        $this->resetErrorBag();
    }

    public function save(AuditLogger $audit): void
    {
        abort_unless($this->canManage(), 403);

        $term = $this->term();
        abort_if($term === null, 404);

        $data = $this->validate([
            'type' => ['required', Rule::in(array_map(fn (AcademicEventType $type): string => $type->value, AcademicEventType::storable()))],
            'title' => ['nullable', 'string', 'max:150'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [], ['type' => 'jenis agenda', 'title' => 'judul', 'starts_on' => 'tanggal mulai', 'ends_on' => 'tanggal selesai', 'notes' => 'catatan']);

        $attributes = [...$data, 'title' => $data['title'] ?: null, 'notes' => $data['notes'] ?: null];

        if ($this->editingId) {
            $event = AcademicEvent::query()->where('academic_term_id', $term->id)->findOrFail($this->editingId);
            $before = $event->only(array_keys($attributes));
            $event->update($attributes);
            $audit->log('calendar.updated', $event, $before, $attributes);
        } else {
            $event = AcademicEvent::create($attributes + ['tenant_id' => $term->tenant_id, 'academic_term_id' => $term->id]);
            $audit->log('calendar.created', $event, [], $attributes);
        }

        $this->cancel();
        $this->dispatch('toast', message: 'Agenda disimpan.', type: 'success');
    }

    public function delete(int $eventId, AuditLogger $audit): void
    {
        abort_unless($this->canManage(), 403);

        $event = AcademicEvent::query()->findOrFail($eventId);
        $audit->log('calendar.deleted', $event, $event->only(['type', 'title', 'starts_on', 'ends_on']));
        $event->delete();

        $this->cancel();
        $this->dispatch('toast', message: 'Agenda dihapus.', type: 'success');
    }

    public function render(AcademicCalendar $calendar)
    {
        $term = $this->term();
        $entries = $term ? $calendar->entries($term) : collect();

        return view('livewire.academic.calendar', [
            'term' => $term,
            'terms' => AcademicTerm::query()->orderByDesc('starts_at')->get(),
            'entries' => $entries,
            'ongoing' => $term ? $calendar->ongoing($term) : collect(),
            'upcoming' => $entries->filter(fn (array $entry): bool => $entry['starts_on']->isFuture())->take(3),
            'storableTypes' => AcademicEventType::storable(),
            'canManage' => $this->canManage(),
        ]);
    }
}
