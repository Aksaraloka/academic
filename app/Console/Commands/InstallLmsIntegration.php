<?php

namespace App\Console\Commands;

use App\Services\IntegrationApps;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Laravel\Passport\ClientRepository;

/**
 * Alias lama `integration:install-lms`; sama dengan `integration:install lms`.
 */
#[Signature('integration:install-lms {--rotate : Buat ulang rahasia klien yang sudah ada}')]
#[Description('Membuat klien OAuth (SSO dan server) untuk LMS Aksaraloka, lalu mencetak kredensialnya')]
class InstallLmsIntegration extends InstallIntegrationApp
{
    public function handle(ClientRepository $clients, IntegrationApps $apps): int
    {
        return $this->install(IntegrationApps::LMS, $clients, $apps);
    }
}
