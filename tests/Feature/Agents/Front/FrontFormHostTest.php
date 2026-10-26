<?php

use App\Core\Front\FrontSite;

it('accepts a form host or one subdomain label under it, the hosts the CSP wildcard matches', function (string $url, bool $allowed) {
    expect(FrontSite::isFormHostUrl($url))->toBe($allowed);
})->with([
    'apex' => ['https://aiformflow.com/embed/v1/form.js', true],
    'one label' => ['https://forms.aiformflow.com/embed/v1/form.js', true],
    'one label on the second host' => ['https://example-co.aiff.dev/contact', true],
    'two labels' => ['https://a.b.aiformflow.com/embed/v1/form.js', false],
    'two labels on the second host' => ['https://x.example-co.aiff.dev/contact', false],
    'empty label' => ['https://.aiformflow.com/form.js', false],
    'label ending in a hyphen' => ['https://forms-.aiformflow.com/form.js', false],
    'lookalike host' => ['https://evilaiformflow.com/form.js', false],
    'suffix host' => ['https://aiformflow.com.evil.example/form.js', false],
    'http' => ['http://aiformflow.com/form.js', false],
]);
