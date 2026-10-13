<?php

use App\Http\Controllers\FeedbackAttachmentController;
use App\Http\Controllers\GraduationMinutesPrintController;
use App\Http\Controllers\NewsPosterController;
use App\Http\Controllers\ReportExportController;
use App\Http\Controllers\SurveyExportController;
use App\Livewire\Attendance;
use App\Livewire\Auth\Login;
use App\Livewire\Checkin;
use App\Livewire\Dashboard;
use App\Livewire\Feedback;
use App\Livewire\Groups;
use App\Livewire\Leaves;
use App\Livewire\News;
use App\Livewire\Programs;
use App\Livewire\Reports;
use App\Livewire\Schedule;
use App\Livewire\Students;
use App\Livewire\Survey;
use App\Livewire\Teachers;
use App\Livewire\Wisuda;
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
    Route::livewire('/beranda', News\Index::class)->name('dashboard');
    Route::livewire('/ringkasan', Dashboard::class)->name('summary');

    Route::livewire('/survei', Survey\Home::class)->name('survey.home');
    Route::livewire('/survei/kuesioner', Survey\Index::class)->name('survey.index');
    Route::livewire('/survei/bantuan', Survey\Help::class)->name('survey.help');

    Route::middleware('role:admin')->group(function () {
        Route::livewire('/survei/kelola/baru', Survey\Form::class)->name('survey.create');
        Route::livewire('/survei/{survey}/ubah', Survey\Form::class)->name('survey.edit');
    });

    Route::middleware('role:admin,principal')->group(function () {
        Route::livewire('/survei/{survey}/hasil', Survey\Results::class)->name('survey.results');
        Route::get('/survei/{survey}/hasil/ekspor', SurveyExportController::class)->name('survey.export');
    });

    Route::livewire('/survei/{survey}/isi', Survey\Fill::class)->name('survey.fill');

    Route::livewire('/masukan', Feedback\Index::class)->name('feedback.index');
    Route::livewire('/masukan/baru', Feedback\Create::class)->name('feedback.create');
    Route::livewire('/masukan/{ticket}', Feedback\Show::class)->name('feedback.show');
    Route::get('/masukan/{ticket}/lampiran', FeedbackAttachmentController::class)->name('feedback.attachment');

    Route::middleware('role:admin')->group(function () {
        Route::livewire('/berita/kelola', News\Manage::class)->name('news.manage');
    });

    Route::livewire('/berita/{news}', News\Show::class)->name('news.show');
    Route::get('/berita/{news}/poster', NewsPosterController::class)->name('news.poster');

    Route::middleware('role:admin,principal,curriculum,teacher,duty')->group(function () {
        Route::livewire('/mahasiswa', Students\Index::class)->name('students.index');
        Route::livewire('/kelas', Groups\Index::class)->name('groups.index');
        Route::livewire('/kelas/{group}', Groups\Show::class)->name('groups.show');
    });

    Route::middleware('role:admin')->group(function () {
        Route::get('/mahasiswa/templat', fn () => response(StudentImporter::template(), 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="templat-mahasiswa.csv"',
        ]))->name('students.template');
        Route::livewire('/mahasiswa/baru', Students\Form::class)->name('students.create');
        Route::livewire('/mahasiswa/impor', Students\Import::class)->name('students.import');
        Route::livewire('/mahasiswa/{student}/ubah', Students\Form::class)->name('students.edit');
        Route::livewire('/dosen', Teachers\Index::class)->name('teachers.index');
        Route::livewire('/prodi', Programs\Index::class)->name('programs.index');
    });

    Route::livewire('/jadwal', Schedule\Index::class)->name('schedule.index');

    Route::middleware('role:admin,curriculum')->group(function () {
        Route::livewire('/jadwal/atur', Schedule\Manage::class)->name('schedule.manage');
    });

    Route::livewire('/presensi-saya', Attendance\Mine::class)->name('attendance.mine')->middleware('role:student');

    Route::middleware('role:admin,principal,teacher,duty')->group(function () {
        Route::livewire('/presensi', Attendance\Index::class)->name('attendance.index');
        Route::livewire('/presensi/{session}', Attendance\Mark::class)->name('attendance.mark');
    });

    Route::middleware('role:principal,curriculum,teacher,duty')->group(function () {
        Route::livewire('/check-in', Checkin::class)->name('checkin');
    });

    Route::middleware('role:admin,principal,curriculum,student')->group(function () {
        Route::livewire('/wisuda', Wisuda\Overview::class)->name('wisuda.overview');
    });

    Route::middleware('role:admin')->group(function () {
        Route::livewire('/wisuda/periode', Wisuda\Periods::class)->name('wisuda.periods');
    });

    Route::middleware('role:admin,principal,curriculum')->group(function () {
        Route::livewire('/wisuda/{period}/pendaftar', Wisuda\Registrations::class)->name('wisuda.registrations');
        Route::livewire('/wisuda/{period}/berita-acara', Wisuda\Minutes::class)->name('wisuda.minutes');
        Route::get('/wisuda/{period}/berita-acara/cetak', GraduationMinutesPrintController::class)->name('wisuda.minutes.print');
    });

    Route::middleware('role:student')->group(function () {
        Route::livewire('/wisuda/{period}/daftar', Wisuda\Register::class)->name('wisuda.register');
    });

    Route::livewire('/rekap', Reports\Index::class)->name('reports.index');
    Route::get('/rekap/ekspor', ReportExportController::class)->name('reports.export');

    Route::livewire('/izin', Leaves\Index::class)->name('leaves.index')->middleware('role:admin,principal,teacher,duty,curriculum');
});
