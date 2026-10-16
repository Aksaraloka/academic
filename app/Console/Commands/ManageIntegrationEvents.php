<?php

namespace App\Console\Commands;

use App\Jobs\DeliverIntegrationEvent;
use App\Models\IntegrationEvent;
use App\Services\LmsIntegration;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('integration:events {--retry-failed : Kirim ulang event yang gagal terkirim ke LMS}')]
#[Description('Melihat antrean webhook ke LMS dan mengirim ulang event yang gagal')]
class ManageIntegrationEvents extends Command
{
    public function handle(LmsIntegration $lms): int
    {
        if (! $lms->isConfigured()) {
            $this->components->warn('Integrasi LMS belum dikonfigurasi (LMS_URL dan LMS_TENANT_ID).');

            return self::SUCCESS;
        }

        $undelivered = IntegrationEvent::query()->where('tenant_id', $lms->tenantId())->whereNull('delivered_at');
        $failed = (clone $undelivered)->whereNotNull('last_error');

        $this->components->info(sprintf(
            '%d event terkirim, %d menunggu, %d gagal.',
            IntegrationEvent::query()->where('tenant_id', $lms->tenantId())->whereNotNull('delivered_at')->count(),
            (clone $undelivered)->whereNull('last_error')->count(),
            $failed->count(),
        ));

        $rows = (clone $failed)->orderBy('created_at')->limit(50)->get();

        if ($rows->isNotEmpty()) {
            $this->table(
                ['ID', 'Tipe', 'Percobaan', 'Dibuat', 'Galat terakhir'],
                $rows->map(fn (IntegrationEvent $event): array => [$event->id, $event->type, $event->attempts, $event->created_at->toDateTimeString(), mb_strimwidth((string) $event->last_error, 0, 60, '…')])->all(),
            );
        }

        if (! $this->option('retry-failed')) {
            return self::SUCCESS;
        }

        if (! $lms->canPublishEvents()) {
            $this->components->error('LMS_WEBHOOK_SECRET belum diisi, event tidak dapat dikirim.');

            return self::FAILURE;
        }

        $retried = 0;

        (clone $failed)->orderBy('created_at')->each(function (IntegrationEvent $event) use (&$retried): void {
            $event->forceFill(['attempts' => 0])->save();
            DeliverIntegrationEvent::dispatch($event->id);
            $retried++;
        });

        $this->components->info("{$retried} event dikirim ulang.");

        return self::SUCCESS;
    }
}
