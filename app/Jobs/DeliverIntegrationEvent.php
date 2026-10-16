<?php

namespace App\Jobs;

use App\Models\IntegrationEvent;
use App\Services\IntegrationEvents;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Mengirim satu event outbox ke LMS. Gagal diulang dengan backoff 1, 5, 15, lalu 60 menit; maksimal 8 percobaan.
 * Event yang habis percobaan tetap tersimpan dan bisa dikirim ulang dengan `integration:events --retry-failed`.
 */
class DeliverIntegrationEvent implements ShouldQueue
{
    use Queueable;

    public const MAX_ATTEMPTS = 8;

    public int $tries = self::MAX_ATTEMPTS;

    public function __construct(public string $eventId)
    {
        $this->afterCommit();
    }

    /**
     * Jeda antar percobaan dalam detik (7 jeda untuk 8 percobaan).
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300, 900, 3600, 3600, 3600, 3600];
    }

    public function handle(IntegrationEvents $events): void
    {
        $event = IntegrationEvent::query()->find($this->eventId);

        if ($event === null || $event->isDelivered() || ! $events->enabled()) {
            return;
        }

        $events->deliver($event);
    }

    public function failed(Throwable $exception): void
    {
        IntegrationEvent::query()->whereKey($this->eventId)->whereNull('delivered_at')
            ->update(['last_error' => mb_substr($exception->getMessage(), 0, 1000)]);
    }
}
