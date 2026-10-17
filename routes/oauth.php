<?php

use Illuminate\Support\Facades\Route;
use Laravel\Passport\Http\Controllers\AccessTokenController;

/*
|--------------------------------------------------------------------------
| Endpoint token OAuth
|--------------------------------------------------------------------------
|
| Dimuat tanpa grup middleware `web` (seperti route bawaan Passport): klien memakai client_id dan
| client_secret atau kode otorisasi, bukan sesi dan cookie, sehingga tidak ada CSRF yang perlu dilindungi.
|
*/

Route::post('/oauth/token', [AccessTokenController::class, 'issueToken'])->middleware('throttle:60,1')->name('passport.token');
