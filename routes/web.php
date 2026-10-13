<?php

use App\Http\Controllers\ReportExportController;
use App\Livewire\Attendance;
use App\Livewire\Auth\Login;
use App\Livewire\Checkin;
use App\Livewire\Dashboard;
use App\Livewire\Groups;
use App\Livewire\Leaves;
use App\Livewire\Reports;
use App\Livewire\Scan;
use App\Livewire\Schedule;
use App\Livewire\Students;
use App\Livewire\Teachers;
use App\Services\StudentImporter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/beranda');

Route::middleware('guest')->group(function () {
    Route::livewire('/masuk', Login::class)->name('login');
});

Route::post('/keluar', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect()->route('login');
})->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::livewire('/beranda', Dashboard::class)->name('dashboard');

    Route::middleware('role:admin,principal,curriculum,teacher,duty')->group(function () {
        Route::livewire('/siswa', Students\Index::class)->name('students.index');
        Route::livewire('/rombel', Groups\Index::class)->name('groups.index');
        Route::livewire('/rombel/{group}', Groups\Show::class)->name('groups.show');
    });

    Route::middleware('role:admin')->group(function () {
        Route::get('/siswa/templat', fn () => response(StudentImporter::template(), 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="templat-siswa.csv"',
        ]))->name('students.template');
        Route::livewire('/siswa/baru', Students\Form::class)->name('students.create');
        Route::livewire('/siswa/impor', Students\Import::class)->name('students.import');
        Route::livewire('/siswa/{student}/ubah', Students\Form::class)->name('students.edit');
        Route::livewire('/guru', Teachers\Index::class)->name('teachers.index');
    });

    Route::livewire('/jadwal', Schedule\Index::class)->name('schedule.index');

    Route::middleware('role:admin,curriculum')->group(function () {
        Route::livewire('/jadwal/atur', Schedule\Manage::class)->name('schedule.manage');
    });

    Route::middleware('role:admin,principal,teacher,duty')->group(function () {
        Route::livewire('/presensi', Attendance\Index::class)->name('attendance.index');
        Route::livewire('/presensi/{session}', Attendance\Mark::class)->name('attendance.mark');
        Route::livewire('/presensi/{session}/qr', Attendance\Qr::class)->name('attendance.qr');
    });

    Route::middleware('role:principal,curriculum,teacher,duty')->group(function () {
        Route::livewire('/check-in', Checkin::class)->name('checkin');
    });

    Route::middleware('role:student')->group(function () {
        Route::livewire('/pindai', Scan::class)->name('scan');
    });

    Route::livewire('/rekap', Reports\Index::class)->name('reports.index');
    Route::get('/rekap/ekspor', ReportExportController::class)->name('reports.export');

    Route::livewire('/izin', Leaves\Index::class)->name('leaves.index')->middleware('role:admin,principal,teacher,duty,curriculum');
});
