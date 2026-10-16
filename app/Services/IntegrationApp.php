<?php

namespace App\Services;

use App\Models\User;

/**
 * Satu aplikasi Aksaraloka yang terhubung ke Academic (LMS, Admission). Konfigurasi dibaca saat dipakai dari
 * `integration.apps.{kunci}`; untuk LMS, kunci lama `integration.lms.*` dan `integration.role_map` tetap diutamakan
 * agar konfigurasi dan test sebelum integrasi multi-aplikasi tetap berlaku.
 */
class IntegrationApp
{
    public function __construct(public readonly string $key) {}

    public function label(): string
    {
        return (string) $this->value('label', $this->key);
    }

    public function url(): string
    {
        return rtrim((string) $this->value('url', ''), '/');
    }

    public function tenantId(): ?int
    {
        $tenantId = $this->value('tenant_id');

        return $tenantId === null || $tenantId === '' ? null : (int) $tenantId;
    }

    public function webhookSecret(): string
    {
        return (string) $this->value('webhook_secret', '');
    }

    /**
     * Alamat penerima webhook di aplikasi ini.
     */
    public function webhookUrl(): string
    {
        return $this->url().'/webhooks/academic';
    }

    public function isConfigured(): bool
    {
        return $this->url() !== '' && $this->tenantId() !== null;
    }

    /**
     * Webhook hanya dikirim bila aplikasi terkonfigurasi dan rahasia penandatangan tersedia.
     */
    public function canPublishEvents(): bool
    {
        return $this->isConfigured() && $this->webhookSecret() !== '';
    }

    /**
     * Peran Academic yang boleh SSO => peran padanan di aplikasi ini.
     *
     * @return array<string, string>
     */
    public function ssoRoles(): array
    {
        $roles = $this->key === 'lms' ? config('integration.role_map') : null;

        return (array) ($roles ?? $this->value('sso_roles', []));
    }

    /**
     * Scope yang boleh diminta klien aplikasi ini.
     *
     * @return array<int, string>
     */
    public function scopes(): array
    {
        return array_values((array) $this->value('scopes', []));
    }

    public function allowsScope(string $scope): bool
    {
        return in_array($scope, $this->scopes(), true);
    }

    /**
     * Peran padanan di aplikasi ini, atau null bila peran pengguna tidak punya akses.
     */
    public function roleFor(User $user): ?string
    {
        return $this->ssoRoles()[$user->role->value] ?? null;
    }

    /**
     * Apakah pengguna ini boleh memakai aplikasi: satu tenant dengan integrasi dan perannya terdaftar.
     */
    public function canAccess(User $user): bool
    {
        return $this->tenantId() !== null
            && $user->tenant_id === $this->tenantId()
            && $this->roleFor($user) !== null;
    }

    /**
     * Alasan akses ditolak (untuk pesan galat), atau null bila diizinkan.
     */
    public function denialReason(User $user): ?string
    {
        if ($this->tenantId() === null) {
            return "Integrasi {$this->label()} belum disiapkan.";
        }

        if ($user->tenant_id !== $this->tenantId()) {
            return "Akun ini tidak terdaftar pada kampus yang terhubung ke {$this->label()}.";
        }

        if ($this->roleFor($user) === null) {
            return "Peran akun ini tidak punya akses ke {$this->label()}.";
        }

        return null;
    }

    private function value(string $name, mixed $default = null): mixed
    {
        $legacy = $this->key === 'lms' ? config("integration.lms.{$name}") : null;

        return $legacy ?? config("integration.apps.{$this->key}.{$name}", $default);
    }
}
