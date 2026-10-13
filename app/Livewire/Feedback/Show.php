<?php

namespace App\Livewire\Feedback;

use App\Enums\FeedbackStatus;
use App\Models\FeedbackTicket;
use App\Services\FeedbackService;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Detail masukan')]
class Show extends Component
{
    public FeedbackTicket $ticket;

    public string $body = '';

    public string $status = '';

    public function mount(FeedbackTicket $ticket): void
    {
        abort_unless(FeedbackTicket::query()->visibleTo(auth()->user())->whereKey($ticket->id)->exists(), 404);

        $this->ticket = $ticket;
        $this->status = $ticket->status->value;
    }

    public function reply(FeedbackService $service): void
    {
        $data = $this->validate(['body' => ['required', 'string', 'min:2', 'max:3000']], [], ['body' => 'balasan']);

        $service->reply($this->ticket, auth()->user(), $data['body']);

        $this->ticket->refresh();
        $this->status = $this->ticket->status->value;
        $this->reset('body');
        $this->dispatch('toast', message: 'Balasan terkirim.', type: 'success');
    }

    public function updateStatus(FeedbackService $service): void
    {
        $this->validate(['status' => ['required', Rule::enum(FeedbackStatus::class)]]);

        $service->changeStatus($this->ticket, auth()->user(), FeedbackStatus::from($this->status));

        $this->ticket->refresh();
        $this->dispatch('toast', message: 'Status diperbarui.', type: 'success');
    }

    public function render()
    {
        $user = auth()->user();

        return view('livewire.feedback.show', [
            'replies' => $this->ticket->replies()->with('user')->get(),
            'isHandler' => FeedbackTicket::isHandler($user),
            'statuses' => FeedbackStatus::cases(),
            'sender' => $this->ticket->senderLabelFor($user),
            'handler' => $this->ticket->handler,
        ]);
    }
}
