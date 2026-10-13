<?php

use App\Enums\Role;
use App\Livewire\Auth\Login;
use App\Livewire\Students\Import;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

it('redirects guests to the login page', function () {
    $this->get('/beranda')->assertRedirect('/masuk');
});

it('logs in with valid credentials and rejects invalid ones', function () {
    $user = User::factory()->role(Role::Admin)->create(['email' => 'admin@test.id']);

    Livewire::test(Login::class)
        ->set('email', 'admin@test.id')->set('password', 'salah')
        ->call('login')
        ->assertHasErrors('email');

    Livewire::test(Login::class)
        ->set('email', 'admin@test.id')->set('password', 'password')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

it('renders the dashboard for every role', function (Role $role) {
    $user = User::factory()->role($role)->create();

    $this->actingAs($user)->get('/beranda')->assertOk()->assertSee('Halo');
})->with(Role::cases());

it('blocks roles from pages they may not open', function () {
    $student = User::factory()->role(Role::Student)->create();
    $teacher = User::factory()->role(Role::Teacher)->create();

    $this->actingAs($student)->get('/mahasiswa')->assertForbidden();
    $this->actingAs($student)->get('/presensi')->assertForbidden();
    $this->actingAs($teacher)->get('/mahasiswa/baru')->assertForbidden();
    $this->actingAs($teacher)->get('/jadwal/atur')->assertForbidden();
});

it('scopes students to the signed-in tenant', function () {
    $admin = User::factory()->role(Role::Admin)->create();
    Student::factory()->create(['tenant_id' => $admin->tenant_id, 'name' => 'Mahasiswa Sendiri']);
    Student::factory()->create(['name' => 'Mahasiswa Kampus Lain']);

    $this->actingAs($admin)->get('/mahasiswa')->assertSee('Mahasiswa Sendiri')->assertDontSee('Mahasiswa Kampus Lain');
});

it('imports students from a CSV and reports bad rows', function () {
    $admin = User::factory()->role(Role::Admin)->create();
    Student::factory()->create(['tenant_id' => $admin->tenant_id, 'nim' => '1001']);

    $csv = "nim,nama,jenis_kelamin,tanggal_lahir,alamat,telepon,prodi,angkatan,kelas\n"
        ."1001,Duplikat Lama,L,,,,,,\n"
        ."2001,Mahasiswa Baru,P,2005-01-02,,,Teknik Informatika,2024,\n"
        ."2001,Duplikat Dalam Berkas,L,,,,,,\n"
        .",Tanpa NIM,L,,,,,,\n";

    $file = UploadedFile::fake()->createWithContent('mahasiswa.csv', $csv);

    Livewire::actingAs($admin)->test(Import::class)
        ->set('file', $file)
        ->assertSet('preview.valid.0.nama', 'Mahasiswa Baru')
        ->assertCount('preview.errors', 3)
        ->call('confirm')
        ->assertDispatched('toast');

    expect(Student::query()->where('nim', '2001')->exists())->toBeTrue();
});

it('keeps tenants separated when creating records', function () {
    $tenantA = Tenant::factory()->create();
    $admin = User::factory()->for($tenantA)->role(Role::Admin)->create();

    $this->actingAs($admin);
    $student = Student::create(['name' => 'Baru', 'nim' => '9']);

    expect($student->tenant_id)->toBe($tenantA->id);
});
