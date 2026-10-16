<?php

namespace App\Services;

use App\Models\User;

/**
 * Konfigurasi dan aturan akses integrasi dengan LMS Aksaraloka.
 */
class LmsIntegration
{
    public function url(): string
    {
        return (string) config('integration.lms.url');
    }

    public function isConfigured(): bool
    {
        return $this->url() !== '' && $this->tenantId() !== null;
    }

    public function tenantId(): ?int
    {
        return config('integration.lms.tenant_id');
    }

    /**
     * Tautan ke kelas mata kuliah di LMS.
     */
    public function classUrl(int $teachingAssignmentId): string
    {
        return $this->url().'/kelas/academic/'.$teachingAssignmentId;
    }

    /**
     * Apakah pengguna ini boleh memakai LMS: satu tenant dengan integrasi dan perannya punya padanan di LMS.
     */
    public function canAccess(User $user): bool
    {
        return $this->tenantId() !== null
            && $user->tenant_id === $this->tenantId()
            && $this->lmsRole($user) !== null;
    }

    /**
     * Peran padanan di LMS, atau null bila peran ini tidak punya akses LMS.
     */
    public function lmsRole(User $user): ?string
    {
        return config('integration.role_map.'.$user->role->value);
    }

    /**
     * Alasan akses ditolak (untuk pesan galat), atau null bila diizinkan.
     */
    public function denialReason(User $user): ?string
    {
        if ($this->tenantId() === null) {
            return 'Integrasi Kelas Online belum disiapkan.';
        }

        if ($user->tenant_id !== $this->tenantId()) {
            return 'Akun ini tidak terdaftar pada kampus yang terhubung ke Kelas Online.';
        }

        if ($this->lmsRole($user) === null) {
            return 'Peran akun ini tidak punya akses ke Kelas Online.';
        }

        return null;
    }
}
