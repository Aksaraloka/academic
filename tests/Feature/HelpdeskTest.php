<?php

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->student = User::where('email', 'mahasiswa@aksaraloka.test')->firstOrFail();
    $this->tenant = $this->student->tenant;
});

it('shows the WhatsApp helpdesk button under Masukan in the sidebar for every role', function () {
    foreach (['admin@', 'kaprodi@', 'akademik@', 'dosen@', 'petugas@', 'mahasiswa@', 'ortu@'] as $prefix) {
        $html = $this->actingAs(User::where('email', $prefix.'aksaraloka.test')->firstOrFail())->get('/home')->assertOk()->getContent();

        expect($html)->toContain('Hubungi Helpdesk')
            ->toContain('https://wa.me/6281234567800?text=')
            ->toContain('target="_blank" rel="noopener noreferrer"')
            ->and(strpos($html, 'Hubungi Helpdesk'))->toBeGreaterThan(strpos($html, 'Masukan'));
    }
});

it('opens WhatsApp with a message containing the sender name, role and NIM', function () {
    $url = $this->tenant->helpdeskUrl($this->student);
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    expect($url)->toStartWith('https://wa.me/6281234567800?text=')
        ->and($query['text'])->toContain('Halo Helpdesk Universitas Aksara Nusantara')
        ->toContain($this->student->name)->toContain('Mahasiswa')->toContain('NIM '.$this->student->student->nim);
});

it('leaves out the NIM for non-students', function () {
    $lecturer = User::where('email', 'dosen@aksaraloka.test')->firstOrFail();
    parse_str((string) parse_url($this->tenant->helpdeskUrl($lecturer), PHP_URL_QUERY), $query);

    expect($query['text'])->toContain($lecturer->name)->toContain('Dosen')->not->toContain('NIM');
});

it('normalizes Indonesian phone number formats', function (string $input) {
    $tenant = new Tenant(['settings' => ['helpdesk_whatsapp' => $input]]);

    expect($tenant->helpdeskNumber())->toBe('6281234567800');
})->with(['081234567800', '0812-3456-7800', '+62 812-3456-7800', '81234567800', '6281234567800', '(0812) 3456 7800']);

it('ignores invalid numbers', function (string $input) {
    expect((new Tenant(['settings' => ['helpdesk_whatsapp' => $input]]))->helpdeskNumber())->toBeNull();
})->with(['', 'abc', '123', '0000000000000000000']);

it('falls back to the environment number and lets the campus setting override it', function () {
    config(['services.helpdesk.whatsapp' => '0811111111111']);

    expect((new Tenant(['settings' => []]))->helpdeskNumber())->toBe('62811111111111')
        ->and((new Tenant(['settings' => ['helpdesk_whatsapp' => '081234567800']]))->helpdeskNumber())->toBe('6281234567800');
});

it('hides the button when no helpdesk number is configured', function () {
    $this->tenant->update(['settings' => array_merge($this->tenant->settings, ['helpdesk_whatsapp' => null])]);
    config(['services.helpdesk.whatsapp' => null]);

    $this->actingAs($this->student->refresh())->get('/home')->assertOk()->assertDontSee('Hubungi Helpdesk')->assertDontSee('wa.me');
    $this->actingAs($this->student)->get('/feedback')->assertOk()->assertDontSee('Helpdesk via WhatsApp');
});

it('also offers the helpdesk shortcut on the feedback page', function () {
    $this->actingAs($this->student)->get('/feedback')->assertOk()->assertSee('Helpdesk via WhatsApp')->assertSee('https://wa.me/6281234567800', false);
});

it('does not expose the helpdesk link to guests', function () {
    $this->get('/login')->assertOk()->assertDontSee('wa.me');
});

it('puts the helpdesk link in the mobile more-menu sheet', function () {
    $html = $this->actingAs($this->student)->get('/home')->getContent();

    expect(substr_count($html, 'https://wa.me/6281234567800'))->toBeGreaterThanOrEqual(2);
});
