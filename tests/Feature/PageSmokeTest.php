<?php

use App\Models\AttendanceSession;
use App\Models\StudyGroup;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

beforeEach(fn () => $this->seed(DatabaseSeeder::class));

it('renders every page for its intended roles with seeded data', function (string $email, array $paths) {
    $user = User::where('email', $email)->firstOrFail();

    foreach ($paths as $path) {
        $this->actingAs($user)->get($path)->assertOk();
    }
})->with([
    'admin' => ['admin@aksaraloka.test', ['/home', '/summary', '/graduation', '/graduation/periods', '/news/manage', '/students', '/students/new', '/students/import', '/teachers', '/programs', '/groups', '/schedule', '/schedule/manage', '/attendance', '/reports', '/leaves']],
    'principal' => ['kaprodi@aksaraloka.test', ['/home', '/attendance', '/reports', '/check-in', '/schedule', '/leaves']],
    'curriculum' => ['akademik@aksaraloka.test', ['/home', '/schedule', '/schedule/manage', '/groups', '/reports', '/check-in']],
    'teacher' => ['dosen@aksaraloka.test', ['/home', '/schedule', '/attendance', '/check-in', '/reports', '/leaves']],
    'duty' => ['petugas@aksaraloka.test', ['/home', '/attendance', '/check-in', '/schedule']],
    'student' => ['mahasiswa@aksaraloka.test', ['/home', '/summary', '/schedule', '/my-attendance', '/reports', '/graduation']],
    'guardian' => ['ortu@aksaraloka.test', ['/home', '/summary', '/schedule', '/reports']],
]);

it('renders the group page and attendance sheet', function () {
    $admin = User::where('email', 'admin@aksaraloka.test')->firstOrFail();
    $this->actingAs($admin);

    $group = StudyGroup::firstOrFail();
    $session = AttendanceSession::firstOrFail();

    $this->get(route('groups.show', $group))->assertOk()->assertSee($group->name);
    $this->get(route('attendance.mark', $session))->assertOk();
    $this->get(route('reports.export', ['group' => $group->id, 'month' => now()->format('Y-m')]))->assertOk();
});
