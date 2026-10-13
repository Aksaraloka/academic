<?php

use App\Enums\AttendanceStatus;
use App\Models\AcademicTerm;
use App\Models\AttendanceRecord;
use App\Models\Student;
use App\Models\StudyGroupMember;
use App\Models\User;
use App\Services\AttendanceReport;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->user = User::where('email', 'mahasiswa@aksaraloka.test')->firstOrFail();
    $this->student = $this->user->student;
    $this->report = app(AttendanceReport::class);
});

/**
 * Analitik satu mata kuliah (berdasarkan kode) untuk mahasiswa uji.
 */
function courseRow(string $code): array
{
    $student = test()->student;
    $rows = test()->report->studentCourseAnalytics($student, $student->currentGroup(), AcademicTerm::current(), 75, 16);

    return $rows->first(fn (array $row): bool => $row['subject']->code === $code);
}

/**
 * Paksa status kehadiran seluruh pertemuan satu mata kuliah; sebagian diubah menjadi alpa bila $absences diberikan.
 */
function setCourseAttendance(string $code, ?int $absences = null): void
{
    $student = test()->student;
    $ids = collect(courseRow($code)['history']);
    $records = AttendanceRecord::query()
        ->where('student_id', $student->id)
        ->whereHas('session.schedule.assignment.subject', fn ($query) => $query->where('code', $code))
        ->orderBy('id')
        ->get();

    $records->each(fn ($record, int $index) => $record->update(['status' => $absences !== null && $index < $absences ? AttendanceStatus::Absent : AttendanceStatus::Present]));
}

it('lists every course the class follows this semester with their counts', function () {
    $rows = $this->report->studentCourseAnalytics($this->student, $this->student->currentGroup(), AcademicTerm::current(), 75, 16);

    expect($rows->pluck('subject.code')->sort()->values()->all())->toBe(['IF101', 'IF201', 'MK101'])
        ->and($rows->every(fn (array $row): bool => array_sum($row['counts']) === $row['total'] && $row['history']->count() === $row['total']))->toBeTrue()
        ->and($rows->every(fn (array $row): bool => $row['total'] > 0))->toBeTrue();
});

it('computes percentage, tolerance and the aman level for a perfect record', function () {
    setCourseAttendance('IF101');
    $row = courseRow('IF101');

    expect($row['percent'])->toBe(100)
        ->and($row['missed'])->toBe(0)
        ->and($row['allowed'])->toBe(4)
        ->and($row['remaining'])->toBe(4)
        ->and($row['level'])->toBe('aman');
});

it('warns when only one absence of tolerance is left', function () {
    setCourseAttendance('IF101', 3);
    $row = courseRow('IF101');

    expect($row['missed'])->toBe(3)->and($row['remaining'])->toBe(1)->and($row['level'])->toBe('waspada');
});

it('marks a course as not meeting the requirement once absences exceed the tolerance', function () {
    setCourseAttendance('IF101', 5);
    $row = courseRow('IF101');

    expect($row['missed'])->toBe(5)->and($row['remaining'])->toBe(0)->and($row['level'])->toBe('risiko');
});

it('counts izin and sakit as not attended', function () {
    $record = AttendanceRecord::query()->where('student_id', $this->student->id)
        ->whereHas('session.schedule.assignment.subject', fn ($query) => $query->where('code', 'IF101'))->firstOrFail();

    setCourseAttendance('IF101');
    $record->update(['status' => AttendanceStatus::Sick]);

    $row = courseRow('IF101');
    expect($row['counts']['sakit'])->toBe(1)->and($row['attended'])->toBe($row['total'] - 1)->and($row['missed'])->toBe(1);
});

it('uses the planned meetings setting when more meetings are held than planned', function () {
    $rows = $this->report->studentCourseAnalytics($this->student, $this->student->currentGroup(), AcademicTerm::current(), 75, 2);

    expect($rows->every(fn (array $row): bool => $row['planned'] === max(2, $row['total'])))->toBeTrue();
});

it('ignores other students records', function () {
    $before = courseRow('IF101');
    $other = Student::where('id', '!=', $this->student->id)->firstOrFail();
    AttendanceRecord::where('student_id', $other->id)->update(['status' => AttendanceStatus::Absent]);

    $after = courseRow('IF101');
    expect($after['percent'])->toBe($before['percent'])->and($after['missed'])->toBe($before['missed']);
});

it('renders the student presensi page with per-course analytics', function () {
    setCourseAttendance('MK101', 5);

    $this->actingAs($this->user)->get('/presensi-saya')->assertOk()
        ->assertSee('Presensi saya')->assertSee('Algoritma dan Pemrograman')->assertSee('Basis Data')->assertSee('Kalkulus')
        ->assertSee('Perbandingan kehadiran')->assertSee('Riwayat pertemuan')->assertSee('Tidak memenuhi')->assertSee('sudah melebihi batas 4 pertemuan');
});

it('puts a Presensi link in the student sidebar pointing to the analytics page', function () {
    $this->actingAs($this->user)->get('/beranda')->assertSee(route('attendance.mine'), false)->assertSee('Presensi');
});

it('keeps the analytics page for students only', function () {
    $this->get('/presensi-saya')->assertRedirect('/masuk');

    foreach (['admin@', 'kaprodi@', 'dosen@', 'ortu@'] as $prefix) {
        $this->actingAs(User::where('email', $prefix.'aksaraloka.test')->firstOrFail())->get('/presensi-saya')->assertForbidden();
    }

    $this->actingAs($this->user)->get('/presensi')->assertForbidden();
});

it('shows a friendly empty state for a student without a class', function () {
    StudyGroupMember::where('student_id', $this->student->id)->delete();

    $this->actingAs($this->user)->get('/presensi-saya')->assertOk()->assertSee('Belum ada mata kuliah yang kamu ikuti');
});

it('shows an empty state when no semester is active', function () {
    AcademicTerm::query()->update(['is_active' => false]);

    $this->actingAs($this->user)->get('/presensi-saya')->assertOk()->assertSee('Belum ada semester aktif');
});
