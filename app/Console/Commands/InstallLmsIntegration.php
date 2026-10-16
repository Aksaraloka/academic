<?php

namespace App\Console\Commands;

use App\Models\OauthClient;
use App\Services\LmsIntegration;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

#[Signature('integration:install-lms {--rotate : Buat ulang rahasia klien yang sudah ada}')]
#[Description('Membuat klien OAuth (SSO dan server) untuk LMS Aksaraloka, lalu mencetak kredensialnya')]
class InstallLmsIntegration extends Command
{
    public function handle(ClientRepository $clients, LmsIntegration $lms): int
    {
        if ($lms->url() === '') {
            $this->components->error('LMS_URL belum diisi di .env.');

            return self::FAILURE;
        }

        $this->ensureKeys();

        $redirectUri = $lms->url().'/auth/academic/callback';

        $sso = $this->findClient(config('integration.clients.sso'));
        $ssoSecret = null;

        if ($sso === null) {
            $sso = $clients->createAuthorizationCodeGrantClient(config('integration.clients.sso'), [$redirectUri]);
            $ssoSecret = $sso->plainSecret;
        } else {
            $clients->update($sso, $sso->name, [$redirectUri]);

            if ($this->option('rotate')) {
                $clients->regenerateSecret($sso);
                $ssoSecret = $sso->plainSecret;
            }
        }

        $server = $this->findClient(config('integration.clients.server'));
        $serverSecret = null;

        if ($server === null) {
            $server = $clients->createClientCredentialsGrantClient(config('integration.clients.server'));
            $serverSecret = $server->plainSecret;
        } elseif ($this->option('rotate')) {
            $clients->regenerateSecret($server);
            $serverSecret = $server->plainSecret;
        }

        $this->components->info('Klien OAuth siap. Salin nilai berikut ke .env LMS:');
        $this->line('ACADEMIC_URL='.config('app.url'));
        $this->line('ACADEMIC_SSO_CLIENT_ID='.$sso->getKey());
        $this->line('ACADEMIC_SSO_CLIENT_SECRET='.($ssoSecret ?? '<tidak dapat ditampilkan ulang; jalankan dengan --rotate>'));
        $this->line('ACADEMIC_SERVER_CLIENT_ID='.$server->getKey());
        $this->line('ACADEMIC_SERVER_CLIENT_SECRET='.($serverSecret ?? '<tidak dapat ditampilkan ulang; jalankan dengan --rotate>'));
        $this->line('ACADEMIC_WEBHOOK_SECRET=<sama dengan LMS_WEBHOOK_SECRET di .env ini>');

        return self::SUCCESS;
    }

    private function findClient(string $name): ?OauthClient
    {
        return OauthClient::query()->where('name', $name)->where('revoked', false)->first();
    }

    private function ensureKeys(): void
    {
        $hasKeys = config('passport.private_key') !== null
            || file_exists(Passport::keyPath('oauth-private.key'));

        if (! $hasKeys) {
            $this->call('passport:keys');
        }
    }
}
