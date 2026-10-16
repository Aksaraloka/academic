<?php

use App\Http\Controllers\Integration\MeController;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Http\Middleware\CheckToken;

/*
| API integrasi dengan LMS Aksaraloka. Prefiks `/api` ditambahkan otomatis, jadi path lengkapnya
| `/api/integration/v1/...`. `/me` memakai token pengguna (SSO); sisanya token klien server.
*/

Route::prefix('integration/v1')->name('integration.')->middleware('throttle:integration')->group(function () {
    Route::get('/me', MeController::class)
        ->middleware(['auth:api', CheckToken::using('profile'), 'lms.access'])
        ->name('me');
});
