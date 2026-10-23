<?php

it('serves the web app manifest with the manifest content type', function () {
    $this->get('/site.webmanifest')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/manifest+json')
        ->assertJsonPath('name', 'Shopmade')
        ->assertJsonPath('background_color', '#201e1d')
        ->assertJsonCount(3, 'icons');
});

it('links the icon set from the auth pages', function (string $path) {
    $this->get($path)
        ->assertOk()
        ->assertSee('rel="manifest"', false)
        ->assertSee('rel="apple-touch-icon"', false)
        ->assertSee('<meta name="theme-color" content="#201e1d">', false);
})->with(['/login']);
