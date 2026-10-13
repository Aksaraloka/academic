<?php

use App\Livewire\News\Index;
use App\Livewire\News\Manage;
use App\Models\News;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::where('email', 'admin@aksaraloka.test')->firstOrFail();
    $this->student = User::where('email', 'mahasiswa@aksaraloka.test')->firstOrFail();
});

it('shows the latest university news on the home page for every role', function () {
    foreach (['admin@', 'kaprodi@', 'akademik@', 'dosen@', 'petugas@', 'mahasiswa@', 'ortu@'] as $prefix) {
        $this->actingAs(User::where('email', $prefix.'aksaraloka.test')->firstOrFail())
            ->get('/beranda')
            ->assertOk()
            ->assertSee('Berita terbaru')
            ->assertSee('Program Studi Baru: Sains Data dan Kecerdasan Buatan')
            ->assertSee('Beasiswa Prestasi Akademik 2026/2027 Dibuka');
    }
});

it('redirects the root and guests to the right pages', function () {
    $this->get('/')->assertRedirect('/beranda');
    $this->get('/beranda')->assertRedirect('/masuk');
});

it('orders news from newest to oldest with the newest featured', function () {
    $this->actingAs($this->student)->get('/beranda')
        ->assertSeeInOrder(['Program Studi Baru', 'Beasiswa Prestasi Akademik', 'Pendaftaran Wisuda Periode II Dibuka', 'Jadwal Pengisian KRS']);
});

it('filters news by category', function () {
    Livewire::actingAs($this->student)->test(Index::class)
        ->call('setCategory', 'beasiswa')
        ->assertSee('Beasiswa Prestasi Akademik 2026/2027 Dibuka')
        ->assertSee('Beasiswa Mitra Industri')
        ->assertDontSee('Program Studi Baru: Sains Data')
        ->call('setCategory', 'tidak-ada')
        ->assertSee('Program Studi Baru: Sains Data');
});

it('searches news by title', function () {
    Livewire::actingAs($this->student)->test(Index::class)
        ->set('search', 'pertukaran')
        ->assertSee('Program Pertukaran Mahasiswa')
        ->assertDontSee('Seminar Nasional');
});

it('hides drafts from everyone except admin', function () {
    $this->actingAs($this->student)->get('/beranda')->assertDontSee('Gladi Bersih Wisuda');
    $this->actingAs($this->admin)->get('/beranda')->assertSee('Gladi Bersih Wisuda')->assertSee('Draf');

    $draft = News::where('title', 'Gladi Bersih Wisuda')->firstOrFail();
    $this->actingAs($this->student)->get(route('news.show', $draft))->assertNotFound();
    $this->actingAs($this->admin)->get(route('news.show', $draft))->assertOk();
});

it('shows a news detail with related news from the same category', function () {
    $news = News::where('title', 'Beasiswa Prestasi Akademik 2026/2027 Dibuka')->firstOrFail();

    $this->actingAs($this->student)->get(route('news.show', $news))
        ->assertOk()->assertSee('Beasiswa Prestasi Akademik')->assertSee('Beasiswa Mitra Industri untuk Mahasiswa Semester 5');
});

it('serves posters through an access-checked route', function () {
    $published = News::where('title', 'Beasiswa Prestasi Akademik 2026/2027 Dibuka')->firstOrFail();
    $draft = News::where('title', 'Gladi Bersih Wisuda')->firstOrFail();

    $this->get(route('news.poster', $published))->assertRedirect('/masuk');
    $this->actingAs($this->student)->get(route('news.poster', $published))
        ->assertOk()->assertHeader('Content-Type', 'image/svg+xml')->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->actingAs($this->student)->get(route('news.poster', $draft))->assertNotFound();
    $this->actingAs($this->admin)->get(route('news.poster', $draft))->assertOk();
});

it('lets an admin publish news with an uploaded poster and rejects non-images', function () {
    Livewire::actingAs($this->admin)->test(Manage::class)
        ->set('title', 'Beasiswa Tahfidz')->set('category', 'beasiswa')->set('summary', 'Terbuka untuk semua prodi.')
        ->set('poster', UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf'))
        ->call('save')->assertHasErrors('poster');

    Livewire::actingAs($this->admin)->test(Manage::class)
        ->set('title', 'Beasiswa Tahfidz')->set('category', 'beasiswa')->set('summary', 'Terbuka untuk semua prodi.')
        ->set('poster', UploadedFile::fake()->image('poster.png', 800, 1000))
        ->call('save')->assertHasNoErrors();

    $news = News::where('title', 'Beasiswa Tahfidz')->firstOrFail();
    expect($news->is_published)->toBeTrue()->and($news->published_at)->not->toBeNull()->and($news->category->value)->toBe('beasiswa');
    Storage::disk('local')->assertExists($news->poster_path);
});

it('rejects an unknown category', function () {
    Livewire::actingAs($this->admin)->test(Manage::class)
        ->set('title', 'Judul')->set('category', 'bukan-kategori')->set('summary', 'Ringkasan')
        ->call('save')->assertHasErrors('category');
});

it('deletes the poster file together with the news', function () {
    $news = News::firstOrFail();
    Storage::disk('local')->assertExists($news->poster_path);

    Livewire::actingAs($this->admin)->test(Manage::class)->call('delete', $news->id);

    Storage::disk('local')->assertMissing($news->poster_path);
    expect(News::find($news->id))->toBeNull();
});

it('keeps news management admin-only', function () {
    foreach (['mahasiswa@', 'dosen@', 'kaprodi@'] as $prefix) {
        $this->actingAs(User::where('email', $prefix.'aksaraloka.test')->firstOrFail())->get(route('news.manage'))->assertForbidden();
    }

    $this->actingAs($this->admin)->get(route('news.manage'))->assertOk();
});

it('keeps the daily summary on its own page', function () {
    $this->actingAs($this->student)->get(route('summary'))->assertOk()->assertSee('Ringkasan hari ini');
    $this->actingAs($this->admin)->get(route('summary'))->assertOk()->assertSee('Kehadiran mahasiswa hari ini');
});

it('links a wisuda news item to registration for students while it is open', function () {
    $news = News::where('category', 'wisuda')->firstOrFail();

    $this->actingAs($this->student)->get(route('news.show', $news))->assertOk()->assertSee('Daftar wisuda');
});
