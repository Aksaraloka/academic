<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AcademicNotice extends Notification
{
    use Queueable;

    /**
     * @param  string  $routeName  Nama route tujuan saat notifikasi dibuka.
     * @param  array<string, mixed>  $routeParameters
     * @param  string  $tone  success|danger|warning|info
     */
    public function __construct(
        public string $title,
        public string $body,
        public string $routeName,
        public array $routeParameters = [],
        public string $icon = 'bell',
        public string $tone = 'info',
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'url' => route($this->routeName, $this->routeParameters, false),
            'icon' => $this->icon,
            'tone' => $this->tone,
        ];
    }
}
