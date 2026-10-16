<?php

$lms = [
    'url' => rtrim((string) env('LMS_URL', ''), '/'),
    'tenant_id' => env('LMS_TENANT_ID') !== null && env('LMS_TENANT_ID') !== '' ? (int) env('LMS_TENANT_ID') : null,
    'webhook_secret' => env('LMS_WEBHOOK_SECRET'),
];

return [

    /*
    |--------------------------------------------------------------------------
    | Integrasi aplikasi Aksaraloka lain
    |--------------------------------------------------------------------------
    |
    | Academic menjadi pemilik data induk, penyedia identitas (SSO), dan API bagi aplikasi lain. Tiap aplikasi
    | terikat ke satu kampus (tenant): semua data API dan SSO dibatasi ke `tenant_id` aplikasi itu. Kosongkan
    | `url` untuk mematikan tautan dan webhook aplikasi tersebut.
    |
    |   url             alamat dasar aplikasi (tujuan webhook dan redirect SSO)
    |   tenant_id       satu-satunya kampus yang dilayani
    |   webhook_secret  rahasia penandatangan webhook (HMAC) ke aplikasi itu
    |   sso_roles       peran Academic yang boleh SSO => peran padanan di aplikasi itu
    |   scopes          scope yang boleh diminta klien aplikasi itu
    |
    | Kunci LMS lama (`integration.lms.*`, `integration.role_map`, `integration.clients.*`) tetap dibaca.
    |
    */

    'apps' => [
        'lms' => $lms + [
            'label' => 'Kelas Online',
            'sso_roles' => null, // null = `role_map` di bawah
            'scopes' => ['profile', 'sync.read', 'grades.write'],
        ],

        'admission' => [
            'label' => 'Admission',
            'url' => rtrim((string) env('ADMISSION_URL', ''), '/'),
            'tenant_id' => env('ADMISSION_TENANT_ID') !== null && env('ADMISSION_TENANT_ID') !== '' ? (int) env('ADMISSION_TENANT_ID') : null,
            'webhook_secret' => env('ADMISSION_WEBHOOK_SECRET'),
            // Hanya staf; peran panitia yang lebih rinci dikelola di Admission sendiri.
            'sso_roles' => ['admin' => 'admin', 'principal' => 'principal', 'curriculum' => 'curriculum', 'finance' => 'finance'],
            'scopes' => ['profile', 'admission.read', 'admission.write'],
        ],
    ],

    /*
    | Kompatibilitas LMS: bentuk lama yang masih dipakai konfigurasi dan test sebelumnya.
    */

    'lms' => $lms + [
        'rate_limit_per_minute' => 120,
        'max_per_page' => 200,
    ],

    'rate_limit_per_minute' => 120,

    'max_per_page' => 200,

    /*
    | Nama klien OAuth yang dibuat `php artisan integration:install`. Kunci `sso` dan `server` adalah klien LMS (lama).
    */

    'clients' => [
        'sso' => 'Aksaraloka LMS (SSO)',
        'server' => 'Aksaraloka LMS (Server)',
        'admission' => [
            'sso' => 'Aksaraloka Admission (SSO)',
            'server' => 'Aksaraloka Admission (Server)',
        ],
    ],

    /*
    | Peran Academic yang punya akses LMS, beserta peran padanannya di LMS.
    | Peran yang tidak tercantum (duty, finance) tidak boleh masuk LMS.
    */

    'role_map' => [
        'admin' => 'admin',
        'principal' => 'principal',
        'curriculum' => 'principal',
        'teacher' => 'teacher',
        'student' => 'student',
        'guardian' => 'parent',
    ],

];
