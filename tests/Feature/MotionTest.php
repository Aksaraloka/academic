<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->student = User::where('email', 'mahasiswa@aksaraloka.test')->firstOrFail();
});

it('only enables the motion class when the user has not asked for reduced motion and always removes it', function () {
    $html = $this->actingAs($this->student)->get('/home')->getContent();

    expect($html)
        ->toContain("matchMedia('(prefers-reduced-motion: no-preference)')")
        ->toContain('saveData')
        ->toContain("classList.add('motion')")
        ->toContain("classList.remove('motion')");
});

it('hides page content only inside a no-preference media query so content is never held back', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    expect($css)->toMatch('/@media \(prefers-reduced-motion: no-preference\)\s*\{\s*html\.motion #konten > \* > \* \{\s*opacity: 0;/')
        ->and($css)->toContain('prefers-reduced-motion: reduce');
});

it('loads GSAP lazily with a 600 ms fallback and animates only transform and opacity', function () {
    $js = file_get_contents(resource_path('js/motion.js'));

    expect($js)
        ->toContain("import('gsap')")
        ->not->toMatch("/^import .* from 'gsap'/m")
        ->toContain('FALLBACK_MS = 600')
        ->toContain('MAX_ENTRANCE = 12')
        ->toContain('stagger: 0.05')
        ->toContain("ease: 'power2.out'")
        ->toContain('reducedMotion')
        ->toContain('clearProps');

    // Properti penyebab layout tidak boleh dianimasikan (skill gsap-performance).
    expect($js)->not->toMatch('/\b(width|height|top|left|right|bottom|margin|padding)\s*:/');
});

it('keeps GSAP out of the main bundle', function () {
    $manifest = base_path('public/build/manifest.json');

    if (! is_file($manifest)) {
        $this->markTestSkipped('Aset belum di-build.');
    }

    $entries = json_decode(file_get_contents($manifest), true);
    $app = $entries['resources/js/app.js'];

    expect($app['dynamicImports'] ?? [])->not->toBeEmpty()
        ->and(collect($app['dynamicImports'])->contains(fn (string $key) => str_contains($key, 'gsap')))->toBeTrue();
});

it('marks the animatable parts of shared components', function () {
    $this->actingAs($this->student)->get('/my-attendance')->assertOk()
        ->assertSee('data-countup', false)
        ->assertSee('data-grow', false)
        ->assertSee('data-pop', false);
});

it('exposes the page content as direct children of the page root so every page gets the entrance', function () {
    foreach (['/home', '/summary', '/schedule', '/my-attendance', '/profile', '/feedback', '/survey', '/graduation'] as $path) {
        $html = $this->actingAs($this->student)->get($path)->assertOk()->getContent();

        expect($html)->toContain('<main id="konten"')->and($html)->toContain('wire:id=');
    }
});

it('animates menus, the bottom sheet and toasts with light CSS transitions', function () {
    $html = $this->actingAs($this->student)->get('/home')->getContent();

    expect($html)->toContain('x-transition:enter="transition ease-out duration-150"')
        ->toContain('toast-in');

    $this->actingAs($this->student)->get('/survey')->assertSee('active:scale-[0.98]', false);
});

it('does not hide the login page content', function () {
    $this->get('/login')->assertOk()->assertDontSee('id="konten"', false);
});
