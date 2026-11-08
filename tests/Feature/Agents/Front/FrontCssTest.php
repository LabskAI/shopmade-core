<?php

use App\Core\Front\FrontCss;
use App\Core\Front\FrontMinim;
use Tests\Feature\Agents\Front\FrontFixture;

afterEach(fn () => FrontFixture::cleanup());

it('inlines only the sscss rules a page uses, including page class tokens, and no script', function () {
    FrontFixture::create([
        'pages/home.en.md' => FrontFixture::page(['title' => 'Acme', 'description' => 'Acme.'], "# Acme\n\n:::section class=\"py9 m-c2\"\n## Hello\n:::\n"),
    ]);

    $html = $this->get(route('front.home'))->assertOk()->getContent();
    preg_match('#<style data-sscss>(.*?)</style>#s', $html, $sscss);

    expect($sscss[1] ?? '')->toContain('.py9{padding-block:6rem}')
        ->toContain('@media(min-width:768px){')
        ->toContain('.m-c2{')
        ->toContain('.an-rise')
        ->not->toContain('.py10{')
        ->and($html)->toContain('--ink:var(--color-ink')
        ->not->toContain('<style data-sscss></style>')
        ->not->toMatch('/<script(?![^>]*type="application\/(ld\+)?json")/');
});

it('groups variant rules after the plain ones and skips classes outside the vocabulary', function () {
    $css = FrontCss::utilities('<div class="l-c3 f front-card m-hd"><style>.x{}</style><b class="nope ac"></b></div>');

    expect($css)->toBe('.ac{align-items:center}.f{display:flex}@media(min-width:768px){.m-hd{display:none}}@media(min-width:1024px){.l-c3{grid-template-columns:repeat(3,minmax(0,1fr))}}');
});

it('inlines Minim with the nonce only when the markup has a state tag', function () {
    $plain = '<html><body><p>Hi</p></body></html>';
    $stateful = '<html><body><script type="application/json" data-state="app">{"open":false}</script></body></html>';

    expect(FrontMinim::inline($plain, 'abc'))->toBe($plain)
        ->and(FrontMinim::inline($stateful, 'abc'))->toContain('<script nonce="abc">/*! Minim')
        ->toEndWith('</script></body></html>');
});
