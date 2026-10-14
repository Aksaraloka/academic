<?php

use App\Enums\AttendanceStatus;
use App\Enums\Role;
use App\Livewire\Programs\Index;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Faculty;
use App\Models\Program;
use App\Models\StudyGroup;
use App\Models\User;
use App\Services\AttendanceReport;
use Database\Seeders\DatabaseSeeder;
use Livewire\Livewire;

beforeEach(fn () => $this->seed(DatabaseSeeder::class));

it('uses the campus status set without Terlambat', function () {
    $user = User::where('email', 'dosen@aksaraloka.test')->firstOrFail();

    expect(collect($user->attendanceStatuses())->map->value->all())->toBe(['hadir', 'izin', 'sakit', 'alpa']);
});

it('calculates attendance per course for the exam eligibility rule', function () {
    $student = User::where('email', 'mahasiswa@aksaraloka.test')->firstOrFail()->student;
    $this->actingAs($student->user);

    $sessions = AttendanceSession::whereNotNull('schedule_id')
        ->whereHas('schedule.assignment', fn ($query) => $query->where('study_group_id', $student->currentGroup()->id))
        ->get()
        ->groupBy(fn ($session) => $session->schedule->assignment->subject_id);

    $targetSubject = $sessions->keys()->first();
    AttendanceRecord::where('student_id', $student->id)
        ->whereIn('attendance_session_id', $sessions[$targetSubject]->pluck('id'))
        ->update(['status' => AttendanceStatus::Absent]);

    $row = app(AttendanceReport::class)->studentBySubject($student->id)->firstWhere(fn ($row) => $row['subject']->id === $targetSubject);

    expect($row['percent'])->toBe(0)
        ->and($row['percent'] < (int) $student->user->tenant->setting('min_attendance_percent'))->toBeTrue();
});

it('shows the student their per-course eligibility', function () {
    $student = User::where('email', 'mahasiswa@aksaraloka.test')->firstOrFail();

    $this->actingAs($student)->get('/reports')->assertOk()->assertSee('Per mata kuliah')->assertSee('Algoritma dan Pemrograman');
});

it('lets an admin create a faculty and a program', function () {
    $admin = User::where('email', 'admin@aksaraloka.test')->firstOrFail();

    Livewire::actingAs($admin)->test(Index::class)
        ->set('facultyName', 'Fakultas Hukum')->call('addFaculty')->assertHasNoErrors();

    $faculty = Faculty::where('name', 'Fakultas Hukum')->firstOrFail();

    Livewire::actingAs($admin)->test(Index::class)
        ->set('facultyId', (string) $faculty->id)->set('programName', 'Ilmu Hukum')->set('degree', 'S1')
        ->call('addProgram')->assertHasNoErrors();

    expect(Program::where('name', 'Ilmu Hukum')->exists())->toBeTrue();
});

it('exports the semester recap as CSV with exam eligibility', function () {
    $admin = User::where('email', 'admin@aksaraloka.test')->firstOrFail();
    $group = StudyGroup::firstOrFail();

    $response = $this->actingAs($admin)->get(route('reports.export', ['group' => $group->id]));

    $response->assertOk();
    expect($response->streamedContent())->toContain('Syarat ujian')->not->toContain('Terlambat');
});

it('keeps non-admin staff away from faculty management', function () {
    $lecturer = User::where('email', 'dosen@aksaraloka.test')->firstOrFail();

    expect($lecturer->hasRole(Role::Admin))->toBeFalse();
    $this->actingAs($lecturer)->get('/programs')->assertForbidden();
});
