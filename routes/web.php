<?php

use App\Http\Controllers\FeedbackAttachmentController;
use App\Http\Controllers\GradeReportController;
use App\Http\Controllers\GraduationMinutesPrintController;
use App\Http\Controllers\NewsPosterController;
use App\Http\Controllers\ReportExportController;
use App\Http\Controllers\ScheduleExportController;
use App\Http\Controllers\SurveyExportController;
use App\Livewire\Academic;
use App\Livewire\Attendance;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Checkin;
use App\Livewire\Dashboard;
use App\Livewire\Enrollment;
use App\Livewire\Feedback;
use App\Livewire\Grades;
use App\Livewire\Graduation;
use App\Livewire\Groups;
use App\Livewire\Leaves;
use App\Livewire\News;
use App\Livewire\Profile;
use App\Livewire\Programs;
use App\Livewire\Reports;
use App\Livewire\Schedule;
use App\Livewire\Settings;
use App\Livewire\Students;
use App\Livewire\Survey;
use App\Livewire\Teachers;
use App\Services\StudentImporter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/home');

Route::middleware('guest')->group(function () {
    Route::livewire('/login', Login::class)->name('login');
    Route::livewire('/forgot-password', ForgotPassword::class)->name('password.request');
    Route::livewire('/reset-password/{token}', ResetPassword::class)->name('password.reset');
});

Route::post('/logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect()->route('login');
})->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::livewire('/home', News\Index::class)->name('dashboard');
    Route::livewire('/academic-calendar', Academic\Calendar::class)->name('academic.calendar');
    Route::livewire('/enrollment', Enrollment\Plan::class)->name('enrollment.plan')->middleware('role:student');
    Route::livewire('/enrollment/approvals', Enrollment\Approvals::class)->name('enrollment.approvals')->middleware('role:admin,principal,curriculum,teacher');
    Route::livewire('/summary', Dashboard::class)->name('summary');

    Route::livewire('/profile', Profile\Show::class)->name('profile.show');
    Route::livewire('/profile/edit', Profile\Edit::class)->name('profile.edit');

    Route::livewire('/survey', Survey\Home::class)->name('survey.home');
    Route::livewire('/survey/questionnaires', Survey\Index::class)->name('survey.index');
    Route::livewire('/survey/help', Survey\Help::class)->name('survey.help');

    Route::middleware('role:admin')->group(function () {
        Route::livewire('/survey/manage/new', Survey\Form::class)->name('survey.create');
        Route::livewire('/survey/{survey}/edit', Survey\Form::class)->name('survey.edit');
    });

    Route::middleware('role:admin,principal')->group(function () {
        Route::livewire('/survey/{survey}/results', Survey\Results::class)->name('survey.results');
        Route::get('/survey/{survey}/results/export', SurveyExportController::class)->name('survey.export');
    });

    Route::livewire('/survey/{survey}/fill', Survey\Fill::class)->name('survey.fill');

    Route::livewire('/feedback', Feedback\Index::class)->name('feedback.index');
    Route::livewire('/feedback/new', Feedback\Create::class)->name('feedback.create');
    Route::livewire('/feedback/{ticket}', Feedback\Show::class)->name('feedback.show');
    Route::get('/feedback/{ticket}/attachment', FeedbackAttachmentController::class)->name('feedback.attachment');

    Route::middleware('role:admin')->group(function () {
        Route::livewire('/news/manage', News\Manage::class)->name('news.manage');
    });

    Route::livewire('/news/{news}', News\Show::class)->name('news.show');
    Route::get('/news/{news}/poster', NewsPosterController::class)->name('news.poster');

    Route::middleware('role:admin,principal,curriculum,teacher,duty')->group(function () {
        Route::livewire('/students', Students\Index::class)->name('students.index');
        Route::livewire('/groups', Groups\Index::class)->name('groups.index');
        Route::livewire('/groups/{group}', Groups\Show::class)->name('groups.show');
    });

    Route::middleware('role:admin')->group(function () {
        Route::get('/students/template', fn () => response(StudentImporter::template(), 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="templat-mahasiswa.csv"',
        ]))->name('students.template');
        Route::livewire('/students/new', Students\Form::class)->name('students.create');
        Route::livewire('/students/import', Students\Import::class)->name('students.import');
        Route::livewire('/students/{student}/edit', Students\Form::class)->name('students.edit');
        Route::livewire('/academic-terms', Academic\Terms::class)->name('academic.terms');
        Route::livewire('/settings/campus', Settings\Campus::class)->name('settings.campus');
        Route::livewire('/teachers', Teachers\Index::class)->name('teachers.index');
        Route::livewire('/programs', Programs\Index::class)->name('programs.index');
    });

    Route::livewire('/schedule', Schedule\Index::class)->name('schedule.index');
    Route::get('/schedule/download', ScheduleExportController::class)->name('schedule.export');

    Route::middleware('role:admin,curriculum')->group(function () {
        Route::livewire('/schedule/manage', Schedule\Manage::class)->name('schedule.manage');
    });

    Route::livewire('/my-attendance', Attendance\Mine::class)->name('attendance.mine')->middleware('role:student');

    Route::middleware('role:admin,principal,teacher,duty')->group(function () {
        Route::livewire('/attendance', Attendance\Index::class)->name('attendance.index');
        Route::livewire('/attendance/{session}', Attendance\Mark::class)->name('attendance.mark');
    });

    Route::middleware('role:principal,curriculum,teacher,duty')->group(function () {
        Route::livewire('/check-in', Checkin::class)->name('checkin');
    });

    Route::middleware('role:admin,principal,curriculum,student')->group(function () {
        Route::livewire('/graduation', Graduation\Overview::class)->name('graduation.overview');
    });

    Route::middleware('role:admin')->group(function () {
        Route::livewire('/graduation/periods', Graduation\Periods::class)->name('graduation.periods');
    });

    Route::middleware('role:admin,principal,curriculum')->group(function () {
        Route::livewire('/graduation/{period}/registrations', Graduation\Registrations::class)->name('graduation.registrations');
        Route::livewire('/graduation/{period}/minutes', Graduation\Minutes::class)->name('graduation.minutes');
        Route::get('/graduation/{period}/minutes/print', GraduationMinutesPrintController::class)->name('graduation.minutes.print');
    });

    Route::middleware('role:student')->group(function () {
        Route::livewire('/graduation/{period}/register', Graduation\Register::class)->name('graduation.register');
    });

    Route::middleware('role:student,guardian,admin,principal,curriculum')->group(function () {
        Route::livewire('/grades/khs', Grades\Khs::class)->name('grades.khs');
        Route::livewire('/grades/transcript', Grades\Transcript::class)->name('grades.transcript');
        Route::get('/grades/khs/download', [GradeReportController::class, 'khs'])->name('grades.khs.pdf');
        Route::get('/grades/transcript/download', [GradeReportController::class, 'transcript'])->name('grades.transcript.pdf');
    });

    Route::middleware('role:teacher,curriculum,principal,duty,admin')->group(function () {
        Route::livewire('/grades/input', Grades\Courses::class)->name('grades.input.index');
        Route::livewire('/grades/input/{assignment}', Grades\Sheet::class)->name('grades.input.sheet');
    });

    Route::livewire('/grades/revisions', Grades\Revisions::class)->name('grades.revisions')->middleware('role:teacher,principal,admin');
    Route::livewire('/grades/completeness', Grades\Completeness::class)->name('grades.completeness')->middleware('role:admin,principal,curriculum');
    Route::livewire('/grades/settings', Grades\Settings::class)->name('grades.settings')->middleware('role:admin');

    Route::livewire('/reports', Reports\Index::class)->name('reports.index');
    Route::get('/reports/export', ReportExportController::class)->name('reports.export');

    Route::livewire('/leaves', Leaves\Index::class)->name('leaves.index')->middleware('role:admin,principal,teacher,duty,curriculum');
});
