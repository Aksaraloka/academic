<?php

namespace App\Console\Commands;

use App\Jobs\DeliverIntegrationEvent;
use App\Models\IntegrationEvent;
use App\Services\IntegrationApp;
use App\Services\IntegrationApps;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

#[Signature('integration:events {--retry-failed : Kirim ulang event yang gagal terkirim}')]
#[Description('Melihat antrean webhook ke aplikasi lain (LMS, Admission) dan mengirim ulang event yang gagal')]
class ManageIntegrationEvents extends Command
{
    public function handle(IntegrationApps $apps): int
    {
        $configured = collect($apps->all())->filter(fn (IntegrationApp $app): bool => $app->isConfigured());

        if ($configured->isEmpty()) {
            $this->components->warn('Integrasi belum dikonfigurasi (LMS_URL dan LMS_TENANT_ID, atau ADMISSION_URL dan ADMISSION_TENANT_ID).');

            return self::SUCCESS;
        }

        $scope = fn (IntegrationApp $app): Builder => IntegrationEvent::query()->where('tenant_id', $app->tenantId())->where('app', $app->key);

        $delivered = $pending = $failed = 0;
        $rows = collect();

        foreach ($configured as $app) {
            $delivered += $scope($app)->whereNotNull('delivered_at')->count();
            $pending += $scope($app)->whereNull('delivered_at')->whereNull('last_error')->count();
            $failed += $scope($app)->whereNull('delivered_at')->whereNotNull('last_error')->count();
            $rows = $rows->concat($scope($app)->whereNull('delivered_at')->whereNotNull('last_error')->orderBy('created_at')->limit(50)->get());
        }

        $this->components->info("{$delivered} event terkirim, {$pending} menunggu, {$failed} gagal.");

        if ($rows->isNotEmpty()) {
            $this->table(
                ['ID', 'Aplikasi', 'Tipe', 'Percobaan', 'Dibuat', 'Galat terakhir'],
                $rows->map(fn (IntegrationEvent $event): array => [$event->id, $event->app ?? 'lms', $event->type, $event->attempts, $event->created_at->toDateTimeString(), mb_strimwidth((string) $event->last_error, 0, 60, '…')])->all(),
            );
        }

        if (! $this->option('retry-failed')) {
            return self::SUCCESS;
        }

        foreach ($configured as $app) {
            if (! $app->canPublishEvents()) {
                $this->components->error(strtoupper($app->key).'_WEBHOOK_SECRET belum diisi, event tidak dapat dikirim.');

                return self::FAILURE;
            }
        }

        $retried = 0;

        foreach ($configured as $app) {
            $scope($app)->whereNull('delivered_at')->whereNotNull('last_error')->orderBy('created_at')->each(function (IntegrationEvent $event) use (&$retried): void {
                $event->forceFill(['attempts' => 0])->save();
                DeliverIntegrationEvent::dispatch($event->id);
                $retried++;
            });
        }

        $this->components->info("{$retried} event dikirim ulang.");

        return self::SUCCESS;
    }
}
