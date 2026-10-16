<?php

namespace App\Services;

use App\Jobs\DeliverIntegrationEvent;
use App\Models\IntegrationEvent;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Webhook keluar ke aplikasi lain (LMS, Admission) dengan pola outbox: `publish()` menyimpan event di database (ikut
 * transaksi pemanggil, jadi batal bila transaksi dibatalkan) dan mengantrekan pengiriman setelah commit; `deliver()`
 * menandatangani dan mengirimnya ke aplikasi pemilik event. Isi event sengaja minimal (hanya id); penerima mengambil
 * detailnya lewat API.
 */
class IntegrationEvents
{
    public function __construct(private readonly IntegrationApps $apps) {}

    /**
     * Apakah webhook ke aplikasi ini dapat diterbitkan (terkonfigurasi dan rahasia tersedia).
     */
    public function enabled(string $app = IntegrationApps::LMS): bool
    {
        return $this->apps->get($app)->canPublishEvents();
    }

    /**
     * Apakah ada aplikasi mana pun yang dapat menerima webhook (penjaga murah bagi pengamat model).
     */
    public function anyEnabled(): bool
    {
        foreach ($this->apps->all() as $app) {
            if ($app->canPublishEvents()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Terbitkan event ke aplikasi yang berkepentingan dengan tipe ini (atau hanya ke `$app`). Tidak berbuat apa-apa
     * untuk aplikasi yang tidak terkonfigurasi atau bila data milik kampus lain ($tenantId bukan tenant aplikasi itu).
     * Event identik yang belum pernah dicoba dikirim digabung agar perubahan massal tidak membanjiri penerima.
     *
     * @param  array<string, int|string>  $data
     * @return IntegrationEvent|null event pertama yang diterbitkan (atau yang digabung); null bila tidak ada penerima
     */
    public function publish(string $type, array $data, ?int $tenantId = null, ?string $app = null): ?IntegrationEvent
    {
        $first = null;

        foreach ($app !== null ? [$app] : $this->apps->receiving($type) as $key) {
            $event = $this->publishTo($this->apps->get($key), $type, $data, $tenantId);
            $first ??= $event;
        }

        return $first;
    }

    /**
     * Kirim satu event ke aplikasinya. Mencatat percobaan; melempar exception bila gagal agar antrean mengulang dengan backoff.
     *
     * @throws RuntimeException
     */
    public function deliver(IntegrationEvent $event): void
    {
        if ($event->isDelivered()) {
            return;
        }

        $key = $event->app ?? IntegrationApps::LMS;
        $app = $this->apps->get($key);
        $name = $key === IntegrationApps::LMS ? 'LMS' : $app->label();

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
                'X-Aksaraloka-Signature' => self::signature($timestamp, $body, $app->webhookSecret()),
            ])->withBody($body, 'application/json')->post($app->webhookUrl());
        } catch (\Throwable $e) {
            $this->fail($event, "Tidak dapat menghubungi {$name}: ".$e->getMessage());
        }

        if (! $response->successful()) {
            $this->fail($event, "{$name} menjawab HTTP ".$response->status());
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
     * @param  array<string, int|string>  $data
     */
    private function publishTo(IntegrationApp $app, string $type, array $data, ?int $tenantId): ?IntegrationEvent
    {
        if (! $app->canPublishEvents() || ($tenantId !== null && $tenantId !== $app->tenantId())) {
            return null;
        }

        $pending = IntegrationEvent::query()
            ->where('tenant_id', $app->tenantId())->where('app', $app->key)->where('type', $type)->whereNull('delivered_at')->where('attempts', 0)
            ->get()->first(fn (IntegrationEvent $event): bool => $event->payload === $data);

        if ($pending !== null) {
            return $pending;
        }

        $event = IntegrationEvent::create(['tenant_id' => $app->tenantId(), 'app' => $app->key, 'type' => $type, 'payload' => $data, 'attempts' => 0]);

        DeliverIntegrationEvent::dispatch($event->id)->afterCommit();

        return $event;
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
