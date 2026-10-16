<?php

namespace App\Services;

use App\Jobs\DeliverIntegrationEvent;
use App\Models\IntegrationEvent;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Webhook keluar ke LMS dengan pola outbox: `publish()` menyimpan event di database (ikut transaksi pemanggil, jadi
 * batal bila transaksi dibatalkan) dan mengantrekan pengiriman setelah commit; `deliver()` menandatangani dan mengirimnya.
 * Isi event sengaja minimal (hanya id); LMS mengambil detailnya lewat API.
 */
class IntegrationEvents
{
    public function __construct(private readonly LmsIntegration $lms) {}

    public function enabled(): bool
    {
        return $this->lms->canPublishEvents();
    }

    /**
     * Terbitkan event. Tidak berbuat apa-apa bila integrasi tidak terkonfigurasi atau bila data milik kampus lain
     * ($tenantId bukan tenant integrasi). Event identik yang belum pernah dicoba dikirim digabung agar perubahan
     * massal (mis. banyak mahasiswa dipindah kelas) tidak membanjiri LMS.
     *
     * @param  array<string, int|string>  $data
     */
    public function publish(string $type, array $data, ?int $tenantId = null): ?IntegrationEvent
    {
        if (! $this->enabled() || ($tenantId !== null && $tenantId !== $this->lms->tenantId())) {
            return null;
        }

        $pending = IntegrationEvent::query()
            ->where('tenant_id', $this->lms->tenantId())->where('type', $type)->whereNull('delivered_at')->where('attempts', 0)
            ->get()->first(fn (IntegrationEvent $event): bool => $event->payload === $data);

        if ($pending !== null) {
            return $pending;
        }

        $event = IntegrationEvent::create(['tenant_id' => $this->lms->tenantId(), 'type' => $type, 'payload' => $data, 'attempts' => 0]);

        DeliverIntegrationEvent::dispatch($event->id)->afterCommit();

        return $event;
    }

    /**
     * Kirim satu event ke LMS. Mencatat percobaan; melempar exception bila gagal agar antrean mengulang dengan backoff.
     *
     * @throws RuntimeException
     */
    public function deliver(IntegrationEvent $event): void
    {
        if ($event->isDelivered()) {
            return;
        }

        $event->increment('attempts');

        $body = json_encode([
            'id' => $event->id,
            'type' => $event->type,
            'occurred_at' => $event->created_at->toIso8601String(),
            'data' => $event->payload,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $timestamp = (string) time();

        try {
            $response = Http::timeout(10)->acceptJson()->withHeaders([
                'X-Aksaraloka-Event' => $event->type,
                'X-Aksaraloka-Event-Id' => $event->id,
                'X-Aksaraloka-Timestamp' => $timestamp,
                'X-Aksaraloka-Signature' => self::signature($timestamp, $body, $this->lms->webhookSecret()),
            ])->withBody($body, 'application/json')->post($this->lms->webhookUrl());
        } catch (\Throwable $e) {
            $this->fail($event, 'Tidak dapat menghubungi LMS: '.$e->getMessage());
        }

        if (! $response->successful()) {
            $this->fail($event, 'LMS menjawab HTTP '.$response->status());
        }

        $event->forceFill(['delivered_at' => now(), 'last_error' => null])->save();
    }

    /**
     * Tanda tangan webhook: `sha256=` + HMAC-SHA256 dari "timestamp.badan" dengan rahasia bersama.
     */
    public static function signature(string $timestamp, string $body, string $secret): string
    {
        return 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }

    /**
     * @throws RuntimeException
     */
    private function fail(IntegrationEvent $event, string $message): never
    {
        $event->forceFill(['last_error' => mb_substr($message, 0, 1000)])->save();

        throw new RuntimeException($message);
    }
}
