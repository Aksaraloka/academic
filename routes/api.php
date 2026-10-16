<?php

use App\Http\Controllers\Integration\ClassGradeController;
use App\Http\Controllers\Integration\ClassRosterController;
use App\Http\Controllers\Integration\MeController;
use App\Http\Controllers\Integration\TermController;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Http\Middleware\CheckToken;
use Laravel\Passport\Http\Middleware\EnsureClientIsResourceOwner;

/*
| API integrasi dengan LMS Aksaraloka. Prefiks `/api` ditambahkan otomatis, jadi path lengkapnya
| `/api/integration/v1/...`. `/me` memakai token pengguna (SSO); sisanya token klien server.
*/

Route::prefix('integration/v1')->name('integration.')->middleware('throttle:integration')->group(function () {
    Route::get('/me', MeController::class)
        ->middleware(['auth:api', CheckToken::using('profile'), 'lms.access'])
        ->name('me');

    // Data induk untuk LMS: hanya token klien server (client credentials) dengan scope `sync.read`.
    // Token pengguna (SSO) ditolak, dan semua data dibatasi ke tenant integrasi.
    Route::middleware(EnsureClientIsResourceOwner::using('sync.read'))->group(function () {
        Route::get('/terms', [TermController::class, 'index'])->name('terms.index');
        Route::get('/terms/{term}/classes', [TermController::class, 'classes'])->whereNumber('term')->name('terms.classes');
        Route::get('/classes/{class}/roster', [ClassRosterController::class, 'roster'])->whereNumber('class')->name('classes.roster');
        Route::get('/classes/{class}/attendance', [ClassRosterController::class, 'attendance'])->whereNumber('class')->name('classes.attendance');
    });

    // Komponen nilai dari LMS: token klien server dengan scope `grades.write`; penulisan lewat GradingService.
    Route::post('/classes/{class}/grades', [ClassGradeController::class, 'store'])
        ->middleware(EnsureClientIsResourceOwner::using('grades.write'))
        ->whereNumber('class')
        ->name('classes.grades');
});
