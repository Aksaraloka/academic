<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Integrasi LMS (Aksaraloka Kelas Online)
    |--------------------------------------------------------------------------
    |
    | Academic menjadi penyedia identitas (SSO) dan API bagi LMS. Integrasi
    | terikat ke satu kampus (tenant): semua data API dan SSO dibatasi ke
    | `tenant_id` di bawah ini. Kosongkan `url` untuk mematikan tautan dan webhook.
    |
    */

    'lms' => [
        'url' => rtrim((string) env('LMS_URL', ''), '/'),
        'tenant_id' => env('LMS_TENANT_ID') !== null && env('LMS_TENANT_ID') !== '' ? (int) env('LMS_TENANT_ID') : null,
        'webhook_secret' => env('LMS_WEBHOOK_SECRET'),
        'rate_limit_per_minute' => 120,
        'max_per_page' => 200,
    ],

    /*
    | Nama klien OAuth yang dibuat `php artisan integration:install-lms`.
    */

    'clients' => [
        'sso' => 'Aksaraloka LMS (SSO)',
        'server' => 'Aksaraloka LMS (Server)',
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
