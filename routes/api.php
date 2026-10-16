<?php

use App\Http\Controllers\Integration\AdmissionStudentController;
use App\Http\Controllers\Integration\AppsController;
use App\Http\Controllers\Integration\ClassGradeController;
use App\Http\Controllers\Integration\ClassRosterController;
use App\Http\Controllers\Integration\MeController;
use App\Http\Controllers\Integration\ProgramController;
use App\Http\Controllers\Integration\TermController;
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

    // Pengalih aplikasi: token pengguna (SSO) dengan scope `profile`; token klien server ditolak oleh guard `api`.
    Route::get('/apps', AppsController::class)
        ->middleware(['auth:api', CheckToken::using('profile'), 'integration.access'])
        ->name('apps');

    // Data induk untuk LMS: hanya token klien server (client credentials) dengan scope `sync.read`.
    // Token pengguna (SSO) ditolak, dan semua data dibatasi ke tenant integrasi.
    // `/terms` juga dapat dibaca klien Admission (scope `admission.read`); data selalu dari tenant aplikasi pemanggil.
    Route::get('/terms', [TermController::class, 'index'])->middleware('integration.client:sync.read,admission.read')->name('terms.index');

    // Prodi dan tarif untuk Admission: klien server dengan scope `admission.read`.
    Route::middleware('integration.client:admission.read')->group(function () {
        Route::get('/programs', [ProgramController::class, 'index'])->name('programs.index');
        Route::get('/programs/{program}/fee-rates', [ProgramController::class, 'feeRates'])->whereNumber('program')->name('programs.fee-rates');
    });

    Route::middleware('integration.client:sync.read')->group(function () {
        Route::get('/terms/{term}/classes', [TermController::class, 'classes'])->whereNumber('term')->name('terms.classes');
        Route::get('/classes/{class}/roster', [ClassRosterController::class, 'roster'])->whereNumber('class')->name('classes.roster');
        Route::get('/classes/{class}/attendance', [ClassRosterController::class, 'attendance'])->whereNumber('class')->name('classes.attendance');
    });

    // Serah terima mahasiswa baru dari Admission: klien server dengan scope `admission.write`.
    Route::post('/admissions/students', [AdmissionStudentController::class, 'store'])
        ->middleware('integration.client:admission.write')
        ->name('admissions.students.store');

    // Komponen nilai dari LMS: token klien server dengan scope `grades.write`; penulisan lewat GradingService.
    Route::post('/classes/{class}/grades', [ClassGradeController::class, 'store'])
        ->middleware('integration.client:grades.write')
        ->whereNumber('class')
        ->name('classes.grades');
});
