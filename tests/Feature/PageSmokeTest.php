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
    'admin' => ['admin@aksaraloka.test', ['/beranda', '/ringkasan', '/wisuda', '/wisuda/periode', '/berita/kelola', '/mahasiswa', '/mahasiswa/baru', '/mahasiswa/impor', '/dosen', '/prodi', '/kelas', '/jadwal', '/jadwal/atur', '/presensi', '/rekap', '/izin']],
    'principal' => ['kaprodi@aksaraloka.test', ['/beranda', '/presensi', '/rekap', '/check-in', '/jadwal', '/izin']],
    'curriculum' => ['akademik@aksaraloka.test', ['/beranda', '/jadwal', '/jadwal/atur', '/kelas', '/rekap', '/check-in']],
    'teacher' => ['dosen@aksaraloka.test', ['/beranda', '/jadwal', '/presensi', '/check-in', '/rekap', '/izin']],
    'duty' => ['petugas@aksaraloka.test', ['/beranda', '/presensi', '/check-in', '/jadwal']],
    'student' => ['mahasiswa@aksaraloka.test', ['/beranda', '/ringkasan', '/jadwal', '/pindai', '/rekap', '/wisuda']],
    'guardian' => ['ortu@aksaraloka.test', ['/beranda', '/ringkasan', '/jadwal', '/rekap']],
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
