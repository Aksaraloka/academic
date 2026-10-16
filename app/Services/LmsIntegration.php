<?php

namespace App\Services;

use App\Models\User;

/**
 * Pembungkus tipis untuk aplikasi LMS di atas `IntegrationApp`: konfigurasi dan aturan akses ada di kelas induk;
 * di sini hanya yang khas LMS (tautan kelas dan nama peran padanan).
 */
class LmsIntegration extends IntegrationApp
{
    public function __construct()
    {
        parent::__construct(IntegrationApps::LMS);
    }

    /**
     * Tautan ke kelas mata kuliah di LMS.
     */
    public function classUrl(int $teachingAssignmentId): string
    {
        return $this->url().'/kelas/academic/'.$teachingAssignmentId;
    }

    /**
     * Peran padanan di LMS, atau null bila peran ini tidak punya akses LMS.
     */
    public function lmsRole(User $user): ?string
    {
        return $this->roleFor($user);
    }
}
