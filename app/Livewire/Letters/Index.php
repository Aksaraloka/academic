<?php

namespace App\Livewire\Letters;

use App\Enums\LetterStatus;
use App\Enums\LetterType;
use App\Models\LetterRequest;
use App\Services\LetterService;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Permohonan surat mahasiswa')]
class Index extends Component
{
    #[Url(as: 'status')]
    public string $status = 'submitted';

    #[Url(as: 'type')]
    public string $type = '';

    #[Url(as: 'search')]
    public string $search = '';

    public ?int $reviewingId = null;

    public string $note = '';

    public function mount(LetterService $service): void
    {
        abort_unless($service->isManager(auth()->user()), 403);
    }

    public function review(int $id): void
    {
        $this->reviewingId = LetterRequest::query()->findOrFail($id)->id;
        $this->note = '';
        $this->resetErrorBag();
    }

    public function approve(LetterService $service): void
    {
        $this->validate(['note' => ['nullable', 'string', 'max:500']], [], ['note' => 'catatan']);

        $service->approve(LetterRequest::query()->findOrFail($this->reviewingId), auth()->user(), $this->note);

        $this->reset(['reviewingId', 'note']);
        $this->dispatch('toast', message: 'Surat disetujui dan diterbitkan.', type: 'success');
    }

    public function reject(LetterService $service): void
    {
        $this->validate(['note' => ['required', 'string', 'min:5', 'max:500']], [], ['note' => 'alasan penolakan']);

        $service->reject(LetterRequest::query()->findOrFail($this->reviewingId), auth()->user(), $this->note);

        $this->reset(['reviewingId', 'note']);
        $this->dispatch('toast', message: 'Permohonan ditolak.', type: 'success');
    }

    public function render()
    {
        $keyword = trim($this->search);
        $counts = LetterRequest::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $letters = LetterRequest::query()->with('student.program')
            ->when(LetterStatus::tryFrom($this->status), fn ($query, $status) => $query->where('status', $status))
            ->when(LetterType::tryFrom($this->type), fn ($query, $type) => $query->where('type', $type))
            ->when($keyword !== '', fn ($query) => $query->where(fn ($inner) => $inner->where('purpose', 'like', "%{$keyword}%")->orWhere('number', 'like', "%{$keyword}%")
                ->orWhereHas('student', fn ($student) => $student->where('name', 'like', "%{$keyword}%")->orWhere('nim', 'like', "%{$keyword}%"))))
            ->latest('id')->limit(150)->get();

        return view('livewire.letters.index', [
            'letters' => $letters,
            'counts' => $counts,
            'statuses' => LetterStatus::cases(),
            'types' => LetterType::cases(),
            'reviewing' => $this->reviewingId ? $letters->firstWhere('id', $this->reviewingId) : null,
        ]);
    }
}
