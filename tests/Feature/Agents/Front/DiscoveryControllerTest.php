<?php

use Tests\Feature\Agents\Front\FrontFixture;

afterEach(fn () => FrontFixture::cleanup());

it('publishes robots.txt with the sitemap and the AI policy from facts', function () {
    FrontFixture::create();

    $this->get(route('front.robots'))
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSeeInOrder([
            'User-agent: *',
            'Content-Signal: search=yes, ai-input=yes, ai-train=no',
            'User-agent: OAI-SearchBot',
            'Allow: /site/',
            'User-agent: GPTBot',
            'Disallow: /site/',
            'Sitemap: '.route('front.sitemap'),
        ], false);
});

it('follows facts that allow AI training but not AI search', function () {
    FrontFixture::create(['facts.yaml' => FrontFixture::facts(['discovery' => ['ai_search' => false, 'ai_training' => true]])]);

    $this->get(route('front.robots'))
        ->assertSee('Content-Signal: search=yes, ai-input=no, ai-train=yes', false)
        ->assertSeeInOrder(['User-agent: Claude-SearchBot', 'Disallow: /site/', 'User-agent: ClaudeBot', 'Allow: /site/'], false);
});

it('lists every indexable page in the sitemap with hreflang alternates', function () {
    FrontFixture::create([
        'pages/privacy.en.md' => FrontFixture::page(['title' => 'Privacy', 'description' => 'How we handle data.', 'noindex' => true], '# Privacy'),
    ]);

    $response = $this->get(route('front.sitemap'));

    $xml = simplexml_load_string($response->getContent());
    $xml->registerXPathNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');
    $xml->registerXPathNamespace('x', 'http://www.w3.org/1999/xhtml');
    $spanishServices = route('front.localized', ['es', 'services']);

    $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
    expect(array_map('strval', $xml->xpath('//s:url/s:loc')))->toBe([
        route('front.home'),
        route('front.language', 'es'),
        route('front.page', 'services'),
        $spanishServices,
    ])->and(array_map('strval', $xml->xpath('//s:url[s:loc="'.$spanishServices.'"]/x:link/@hreflang')))->toBe(['en', 'es', 'x-default']);
});

it('describes the business and its pages in llms.txt and llms-full.txt', function () {
    FrontFixture::create();

    $this->get(route('front.llms'))
        ->assertSeeInOrder([
            '# Acme Cleaning',
            '> House and office cleaning in Naples and Estero.',
            '- Phone: +1 239-555-0100',
            '- Service area: Naples, Estero',
            '## Pages (en)',
            '- [Acme Cleaning in Naples]('.route('front.home').'): House and office cleaning in Naples and Estero.',
            '- [Cleaning services]('.route('front.page', 'services').'): House cleaning and office cleaning.',
            '## Pages (es)',
            '- [Full text of every page]('.route('front.llms-full').')',
        ], false)
        ->assertDontSee('100 Example Ave');

    $this->get(route('front.llms-full'))
        ->assertSeeInOrder([
            'Page: Cleaning services',
            'URL: '.route('front.page', 'services'),
            "# Cleaning services\n\nHouse cleaning every week.",
        ], false);
});

it('returns 404 for discovery files when the company has no front', function () {
    FrontFixture::create();
    config(['agents.company' => 'globex']);

    $this->get(route('front.sitemap'))->assertNotFound();
});

it('serves the front without session or CSRF cookies', function () {
    FrontFixture::create();

    $this->get(route('front.home'))
        ->assertCookieMissing(config('session.cookie'))
        ->assertCookieMissing('XSRF-TOKEN');
});
