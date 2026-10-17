<?php

use App\Http\Controllers\ActivityEvidenceController;
use App\Http\Controllers\ExamCardPdfController;
use App\Http\Controllers\FeedbackAttachmentController;
use App\Http\Controllers\GradeReportController;
use App\Http\Controllers\GraduationMinutesPrintController;
use App\Http\Controllers\InvoicePdfController;
use App\Http\Controllers\JournalPdfController;
use App\Http\Controllers\LetterPdfController;
use App\Http\Controllers\LetterVerificationController;
use App\Http\Controllers\NewsPosterController;
use App\Http\Controllers\PaymentProofController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\ReportExportController;
use App\Http\Controllers\ScheduleExportController;
use App\Http\Controllers\SkpiPdfController;
use App\Http\Controllers\StudentPhotoController;
use App\Http\Controllers\SurveyExportController;
use App\Http\Controllers\YudisiumPdfController;
use App\Livewire\Academic;
use App\Livewire\Activities;
use App\Livewire\Advising;
use App\Livewire\Attendance;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Checkin;
use App\Livewire\Conversions;
use App\Livewire\Curricula;
use App\Livewire\Dashboard;
use App\Livewire\Edom;
use App\Livewire\Enrollment;
use App\Livewire\Exams;
use App\Livewire\Feedback;
use App\Livewire\Finance;
use App\Livewire\Grades;
use App\Livewire\Graduation;
use App\Livewire\Groups;
use App\Livewire\Internships;
use App\Livewire\Journals;
use App\Livewire\Leaves;
use App\Livewire\Letters;
use App\Livewire\News;
use App\Livewire\Notifications;
use App\Livewire\Pay;
use App\Livewire\Profile;
use App\Livewire\Programs;
use App\Livewire\Reports;
use App\Livewire\Schedule;
use App\Livewire\Scholarships;
use App\Livewire\Settings;
use App\Livewire\Status;
use App\Livewire\Students;
use App\Livewire\Survey;
use App\Livewire\Teachers;
use App\Livewire\Thesis;
use App\Livewire\Yudisium;
use App\Services\StudentImporter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Http\Controllers\AccessTokenController;
use Laravel\Passport\Http\Controllers\AuthorizationController;

Route::redirect('/', '/home');

Route::middleware('guest')->group(function () {
    Route::livewire('/login', Login::class)->name('login');
    Route::livewire('/forgot-password', ForgotPassword::class)->name('password.request');
    Route::livewire('/reset-password/{token}', ResetPassword::class)->name('password.reset');
});

// Notifikasi pembayaran virtual account dari penyedia pembayaran (ditandatangani HMAC, tanpa sesi).
Route::post('/pay/webhook', PaymentWebhookController::class)->name('pay.webhook');

// Tujuan QR pada surat: publik, hanya menegaskan keaslian surat.
Route::get('/verify/{code}', LetterVerificationController::class)->where('code', '[A-Za-z0-9]{8,24}')->middleware('throttle:60,1')->name('letters.verify');

// SSO untuk LMS (OAuth2 authorization code). Tenant dan peran dicek sebelum kode otorisasi diterbitkan.
Route::get('/oauth/authorize', [AuthorizationController::class, 'authorize'])->middleware(['web', 'lms.access'])->name('passport.authorizations.authorize');
Route::post('/oauth/token', [AccessTokenController::class, 'issueToken'])->middleware('throttle:60,1')->name('passport.token');

Route::post('/logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect()->route('login');
})->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::livewire('/home', News\Index::class)->name('dashboard');
    Route::livewire('/notifications', Notifications\Index::class)->name('notifications.index');
    Route::livewire('/academic-calendar', Academic\Calendar::class)->name('academic.calendar');
    Route::livewire('/enrollment', Enrollment\Plan::class)->name('enrollment.plan')->middleware('role:student');
    Route::livewire('/enrollment/approvals', Enrollment\Approvals::class)->name('enrollment.approvals')->middleware('role:admin,principal,curriculum,teacher');
    Route::livewire('/academic-status', Status\Mine::class)->name('status.mine')->middleware('role:student');
    Route::livewire('/academic-status/manage', Status\Manage::class)->name('status.manage')->middleware('role:admin,principal,curriculum');
    Route::livewire('/advising', Advising\Index::class)->name('advising.index')->middleware('role:admin,principal,curriculum,teacher');
    Route::livewire('/my-advising', Advising\Mine::class)->name('advising.mine')->middleware('role:student');
    Route::livewire('/curricula', Curricula\Index::class)->name('curricula.index')->middleware('role:admin,principal,curriculum');
    Route::livewire('/my-curriculum', Curricula\Mine::class)->name('curricula.mine')->middleware('role:student');
    Route::livewire('/pay', Pay\Index::class)->name('pay.index')->middleware('role:student,guardian');
    Route::livewire('/pay/guide', Pay\Guide::class)->name('pay.guide')->middleware('role:student,guardian,admin,finance,principal');
    Route::livewire('/pay/invoices/{invoice}', Pay\Show::class)->name('pay.show')->middleware('role:student,guardian');
    Route::livewire('/finance', Finance\Index::class)->name('finance.index')->middleware('role:admin,finance,principal');
    Route::livewire('/finance/rates', Finance\Rates::class)->name('finance.rates')->middleware('role:admin,finance');
    Route::livewire('/finance/invoices/{invoice}', Finance\Show::class)->name('finance.show')->middleware('role:admin,finance,principal');
    Route::get('/finance/invoices/{invoice}/pdf', InvoicePdfController::class)->name('finance.pdf');
    Route::get('/finance/payments/{payment}/proof', PaymentProofController::class)->name('finance.proof');
    Route::livewire('/exams', Exams\Index::class)->name('exams.index')->middleware('role:admin,principal,curriculum,teacher');
    Route::livewire('/exams/{exam}/participants', Exams\Participants::class)->name('exams.participants')->middleware('role:admin,principal,curriculum,teacher');
    Route::livewire('/my-exams', Exams\Card::class)->name('exams.card')->middleware('role:student,guardian');
    Route::get('/my-exams/pdf', ExamCardPdfController::class)->name('exams.card.pdf')->middleware('role:student,guardian');
    Route::livewire('/evaluations/periods', Edom\Periods::class)->name('evaluations.periods')->middleware('role:admin,principal,curriculum');
    Route::livewire('/evaluations/results', Edom\Results::class)->name('evaluations.results')->middleware('role:admin,principal,curriculum,teacher');
    Route::livewire('/my-evaluations', Edom\Mine::class)->name('evaluations.mine')->middleware('role:student');
    Route::livewire('/my-evaluations/{assignment}', Edom\Fill::class)->name('evaluations.fill')->middleware('role:student');
    Route::livewire('/journals', Journals\Index::class)->name('journals.index')->middleware('role:admin,principal,curriculum,teacher,student');
    Route::livewire('/journals/{assignment}', Journals\Show::class)->name('journals.show')->middleware('role:admin,principal,curriculum,teacher,student');
    Route::get('/journals/{assignment}/pdf', JournalPdfController::class)->name('journals.pdf')->middleware('role:admin,principal,curriculum,teacher');
    Route::livewire('/thesis', Thesis\Index::class)->name('thesis.index')->middleware('role:admin,principal,curriculum,teacher');
    Route::livewire('/thesis/{thesis}', Thesis\Show::class)->name('thesis.show')->middleware('role:admin,principal,curriculum,teacher');
    Route::livewire('/my-thesis', Thesis\Mine::class)->name('thesis.mine')->middleware('role:student');
    Route::livewire('/yudisium', Yudisium\Index::class)->name('yudisium.index')->middleware('role:admin,principal,curriculum,teacher');
    Route::livewire('/yudisium/{yudisium}', Yudisium\Show::class)->name('yudisium.show')->middleware('role:admin,principal,curriculum,teacher');
    Route::get('/yudisium/{yudisium}/pdf', [YudisiumPdfController::class, 'recap'])->name('yudisium.pdf');
    Route::get('/yudisium/decisions/{decision}/certificate', [YudisiumPdfController::class, 'certificate'])->name('yudisium.certificate');
    Route::livewire('/my-graduation', Yudisium\Mine::class)->name('yudisium.mine')->middleware('role:student');
    Route::livewire('/letters', Letters\Mine::class)->name('letters.mine')->middleware('role:student');
    Route::livewire('/letters/manage', Letters\Index::class)->name('letters.index')->middleware('role:admin,principal,curriculum');
    Route::get('/letters/{letter}/pdf', LetterPdfController::class)->name('letters.pdf')->middleware('role:student,admin,principal,curriculum');
    Route::livewire('/activities', Activities\Mine::class)->name('activities.mine')->middleware('role:student');
    Route::livewire('/activities/manage', Activities\Index::class)->name('activities.index')->middleware('role:admin,principal,curriculum');
    Route::get('/activities/skpi/{student?}', SkpiPdfController::class)->name('activities.skpi')->middleware('role:student,admin,principal,curriculum');
    Route::get('/activities/{activity}/evidence', ActivityEvidenceController::class)->name('activities.evidence')->middleware('role:student,admin,principal,curriculum');
    Route::livewire('/scholarships', Scholarships\Mine::class)->name('scholarships.mine')->middleware('role:student');
    Route::livewire('/scholarships/manage', Scholarships\Manage::class)->name('scholarships.manage')->middleware('role:admin,principal,curriculum');
    Route::livewire('/scholarships/applications', Scholarships\Applications::class)->name('scholarships.applications')->middleware('role:admin,principal,curriculum');
    Route::livewire('/internships', Internships\Mine::class)->name('internships.mine')->middleware('role:student');
    Route::livewire('/internships/manage', Internships\Index::class)->name('internships.index')->middleware('role:admin,principal,curriculum,teacher');
    Route::livewire('/conversions', Conversions\Mine::class)->name('conversions.mine')->middleware('role:student');
    Route::livewire('/conversions/manage', Conversions\Index::class)->name('conversions.index')->middleware('role:admin,principal,curriculum');
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
    Route::get('/students/{student}/photo', StudentPhotoController::class)->whereNumber('student')->name('students.photo');

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
