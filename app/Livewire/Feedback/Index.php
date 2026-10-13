<?php

namespace App\Livewire\Feedback;

use App\Enums\FeedbackStatus;
use App\Enums\FeedbackType;
use App\Models\FeedbackTicket;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Masukan dan komplain')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'status')]
    public string $status = '';

    #[Url(as: 'jenis')]
    public string $type = '';

    public function updated(string $property): void
    {
        if (in_array($property, ['status', 'type'], true)) {
            $this->resetPage();
        }
    }

    public function render()
    {
        $user = auth()->user();
        $isHandler = FeedbackTicket::isHandler($user);

        $tickets = FeedbackTicket::query()
            ->visibleTo($user)
            ->with(['user', 'replies'])
            ->when(FeedbackStatus::tryFrom($this->status), fn ($query, FeedbackStatus $status) => $query->where('status', $status))
            ->when(FeedbackType::tryFrom($this->type), fn ($query, FeedbackType $type) => $query->where('type', $type))
            ->latest('updated_at')
            ->paginate(10);

        $counts = $isHandler
            ? FeedbackTicket::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')
            : collect();

        return view('livewire.feedback.index', [
            'tickets' => $tickets,
            'isHandler' => $isHandler,
            'counts' => $counts,
            'statuses' => FeedbackStatus::cases(),
            'types' => FeedbackType::cases(),
            'filtered' => $this->status !== '' || $this->type !== '',
        ]);
    }
}
