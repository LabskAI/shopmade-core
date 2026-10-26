<?php

use Tests\Feature\Agents\Front\FrontFixture;

afterEach(fn () => FrontFixture::cleanup());

it('serves an asset with its fixed type, a long cache and a sandboxing CSP', function () {
    FrontFixture::create(['assets/art/hero.svg' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"/>']);

    $this->get('/site/assets/art/hero.svg')
        ->assertOk()
        ->assertHeader('Content-Type', 'image/svg+xml')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Cache-Control', 'immutable, max-age=31536000, public')
        ->assertHeader('Content-Security-Policy', "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; font-src 'self'; sandbox");
});

it('serves the well-known icons from assets/icons at the front root', function () {
    FrontFixture::create(['assets/icons/favicon.svg' => '<svg xmlns="http://www.w3.org/2000/svg"/>']);

    $this->get('/site/favicon.svg')->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
    $this->get('/site/favicon.ico')->assertNotFound();
    $this->get(route('front.home'))->assertSee('<link rel="icon" href="'.url('/').'/site/favicon.svg" type="image/svg+xml">', false);
});

it('answers 404 for missing files, unlisted types, hidden files and traversal', function (string $path) {
    $base = FrontFixture::create([
        'assets/ok.png' => 'png',
        'assets/notes.txt' => 'secret',
        'assets/page.html' => '<script>alert(1)</script>',
        'assets/.hidden.svg' => '<svg/>',
    ]);
    file_put_contents($base.'/secret.svg', '<svg/>');

    $this->get('/site/assets/'.$path)->assertNotFound();
})->with([
    'missing' => 'nope.png',
    'text file' => 'notes.txt',
    'html file' => 'page.html',
    'hidden file' => '.hidden.svg',
    'dot-dot' => '../facts.yaml',
    'encoded dot-dot' => '%2e%2e/facts.yaml',
    'double encoded' => '%252e%252e%252ffacts.yaml',
    'outside the front' => '../../secret.svg',
    'backslash' => '..%5Cfacts.yaml',
    'null byte' => 'ok.png%00.svg',
    'folder' => 'icons',
]);

it('never serves a symlinked file or a file inside a symlinked folder', function () {
    $base = FrontFixture::create(['assets/real.svg' => '<svg xmlns="http://www.w3.org/2000/svg"/>']);
    $outside = FrontFixture::temporaryDirectory();
    file_put_contents($outside.'/leak.svg', '<svg/>');

    symlink($outside.'/leak.svg', $base.'/fronts/acme/assets/link.svg');
    symlink($outside, $base.'/fronts/acme/assets/linked');
    symlink($base.'/fronts/acme/assets/real.svg', $base.'/fronts/acme/assets/inside-link.svg');

    $this->get('/site/assets/real.svg')->assertOk();
    $this->get('/site/assets/link.svg')->assertNotFound();
    $this->get('/site/assets/linked/leak.svg')->assertNotFound();
    $this->get('/site/assets/inside-link.svg')->assertNotFound();
});

it('serves nothing when the assets folder itself is a symlink', function () {
    $base = FrontFixture::create();
    $outside = FrontFixture::temporaryDirectory();
    file_put_contents($outside.'/x.svg', '<svg/>');
    symlink($outside, $base.'/fronts/acme/assets');

    $this->get('/site/assets/x.svg')->assertNotFound();
});
