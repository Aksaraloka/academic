<?php

namespace App\Livewire\Activities;

use App\Enums\ActivityCategory;
use App\Enums\ActivityStatus;
use App\Models\StudentActivity;
use App\Services\ActivityService;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Verifikasi aktivitas mahasiswa')]
class Index extends Component
{
    #[Url(as: 'status')]
    public string $status = 'pending';

    #[Url(as: 'category')]
    public string $category = '';

    #[Url(as: 'search')]
    public string $search = '';

    public ?int $reviewingId = null;

    public string $points = '';

    public string $note = '';

    public function mount(ActivityService $service): void
    {
        abort_unless($service->isManager(auth()->user()), 403);
    }

    public function review(int $id, ActivityService $service): void
    {
        $activity = StudentActivity::query()->findOrFail($id);

        $this->reviewingId = $activity->id;
        $this->points = (string) $service->suggestedPoints($activity);
        $this->note = '';
        $this->resetErrorBag();
    }

    public function verify(ActivityService $service): void
    {
        $this->validate(['points' => ['required', 'integer', 'between:0,100'], 'note' => ['nullable', 'string', 'max:500']], [], ['points' => 'poin', 'note' => 'catatan']);

        $service->verify(StudentActivity::query()->findOrFail($this->reviewingId), auth()->user(), (int) $this->points, $this->note);

        $this->reset(['reviewingId', 'points', 'note']);
        $this->dispatch('toast', message: 'Kegiatan diverifikasi.', type: 'success');
    }

    public function reject(ActivityService $service): void
    {
        $this->validate(['note' => ['required', 'string', 'min:5', 'max:500']], [], ['note' => 'alasan penolakan']);

        $service->reject(StudentActivity::query()->findOrFail($this->reviewingId), auth()->user(), $this->note);

        $this->reset(['reviewingId', 'points', 'note']);
        $this->dispatch('toast', message: 'Kegiatan ditolak.', type: 'success');
    }

    public function render()
    {
        $keyword = trim($this->search);
        $counts = StudentActivity::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $activities = StudentActivity::query()->with('student.program')
            ->when(ActivityStatus::tryFrom($this->status), fn ($query, $status) => $query->where('status', $status))
            ->when(ActivityCategory::tryFrom($this->category), fn ($query, $category) => $query->where('category', $category))
            ->when($keyword !== '', fn ($query) => $query->where(fn ($inner) => $inner->where('title', 'like', "%{$keyword}%")->orWhere('organizer', 'like', "%{$keyword}%")
                ->orWhereHas('student', fn ($student) => $student->where('name', 'like', "%{$keyword}%")->orWhere('nim', 'like', "%{$keyword}%"))))
            ->latest('id')->limit(150)->get();

        return view('livewire.activities.index', [
            'activities' => $activities,
            'counts' => $counts,
            'statuses' => ActivityStatus::cases(),
            'categories' => ActivityCategory::cases(),
            'reviewing' => $this->reviewingId ? $activities->firstWhere('id', $this->reviewingId) : null,
        ]);
    }
}
