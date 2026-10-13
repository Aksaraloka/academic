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
    'admin' => ['admin@aksaraloka.test', ['/beranda', '/siswa', '/siswa/baru', '/siswa/impor', '/guru', '/rombel', '/jadwal', '/jadwal/atur', '/presensi', '/rekap', '/izin']],
    'principal' => ['kepsek@aksaraloka.test', ['/beranda', '/presensi', '/rekap', '/check-in', '/jadwal', '/izin']],
    'curriculum' => ['kurikulum@aksaraloka.test', ['/beranda', '/jadwal', '/jadwal/atur', '/rombel', '/rekap', '/check-in']],
    'teacher' => ['guru@aksaraloka.test', ['/beranda', '/jadwal', '/presensi', '/check-in', '/rekap', '/izin']],
    'duty' => ['piket@aksaraloka.test', ['/beranda', '/presensi', '/check-in', '/jadwal']],
    'student' => ['siswa@aksaraloka.test', ['/beranda', '/jadwal', '/pindai', '/rekap']],
    'guardian' => ['ortu@aksaraloka.test', ['/beranda', '/jadwal', '/rekap']],
]);

it('renders the group page, attendance sheet and QR page', function () {
    $admin = User::where('email', 'admin@aksaraloka.test')->firstOrFail();
    $this->actingAs($admin);

    $group = StudyGroup::firstOrFail();
    $session = AttendanceSession::firstOrFail();

    $this->get(route('groups.show', $group))->assertOk()->assertSee($group->name);
    $this->get(route('attendance.mark', $session))->assertOk();
    $this->get(route('attendance.qr', $session))->assertOk();
    $this->get(route('reports.export', ['group' => $group->id, 'bulan' => now()->format('Y-m')]))->assertOk();
});
