<?php

namespace App\Livewire\Notifications;

use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Notifikasi')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'filter')]
    public string $filter = 'all';

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    public function open(string $id): void
    {
        $notification = auth()->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        $this->redirect($notification->data['url'] ?? route('notifications.index'), navigate: true);
    }

    public function markAllAsRead(): void
    {
        auth()->user()->unreadNotifications()->update(['read_at' => now()]);
    }

    public function render()
    {
        $user = auth()->user();
        $query = $this->filter === 'unread' ? $user->unreadNotifications() : $user->notifications();

        return view('livewire.notifications.index', [
            'notifications' => $query->paginate(15),
            'unreadCount' => $user->unreadNotifications()->count(),
        ]);
    }
}
