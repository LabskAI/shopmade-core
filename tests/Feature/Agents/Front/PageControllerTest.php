<?php

use Tests\Feature\Agents\Front\FrontFixture;

afterEach(fn () => FrontFixture::cleanup());

/**
 * @return list<array<string, mixed>>
 */
function frontJsonLd(string $html): array
{
    preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);

    return array_map(fn (string $json): array => json_decode($json, true), $matches[1]);
}

it('renders the home page in the default language with navigation and footer from facts', function () {
    FrontFixture::create();

    $this->get(route('front.home'))
        ->assertSee('<html lang="en">', false)
        ->assertSee('<title>Acme Cleaning in Naples</title>', false)
        ->assertSee('<h1>Clean homes in Naples</h1>', false)
        ->assertSee('<a href="'.route('front.page', 'services').'">Services</a>', false)
        ->assertSee('<a class="call" href="tel:+12395550100">Call +1 239-555-0100</a>', false)
        ->assertSeeInOrder(['Service area', 'Naples', 'Estero', 'Services', 'House cleaning', 'Office cleaning', 'Hours', 'Mon-Fri 08:00-18:00'])
        ->assertSee('--color-accent: #123456;', false)
        ->assertSee('&copy; '.now()->year.' Acme Cleaning LLC', false);
});

it('renders the Spanish home under its language prefix with Spanish strings', function () {
    FrontFixture::create();

    $this->get(route('front.language', 'es'))
        ->assertSee('<html lang="es">', false)
        ->assertSee('<h1>Casas limpias en Naples</h1>', false)
        ->assertSee('aria-current="page">Inicio</a>', false)
        ->assertSee('Llamar +1 239-555-0100')
        ->assertSee('Limpieza de casas')
        ->assertSee('<a href="'.route('front.localized', ['es', 'services']).'">Nuestros servicios</a>', false);
});

it('renders a page with its canonical URL, hreflang alternates and language switcher', function () {
    FrontFixture::create();

    $this->get(route('front.localized', ['es', 'services']))
        ->assertSee('<title>Servicios de limpieza</title>', false)
        ->assertSee('<link rel="canonical" href="'.route('front.localized', ['es', 'services']).'">', false)
        ->assertSee('<link rel="alternate" hreflang="en" href="'.route('front.page', 'services').'">', false)
        ->assertSee('<link rel="alternate" hreflang="x-default" href="'.route('front.page', 'services').'">', false)
        ->assertSee('<a href="'.route('front.page', 'services').'" hreflang="en" lang="en">English</a>', false)
        ->assertSee('aria-current="page">Servicios</a>', false);
});

it('returns the page source with Vary: Accept to clients that prefer markdown', function () {
    FrontFixture::create();

    $response = $this->get(route('front.page', 'services'), ['Accept' => 'text/markdown']);

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8')
        ->assertHeader('Vary', 'Accept');

    expect($response->getContent())
        ->toStartWith("---\ntitle: 'Cleaning services'\n")
        ->toContain("---\n\n# Cleaning services\n\nHouse cleaning every week.\n");
});

it('varies HTML pages on Accept and links the discovery files', function () {
    FrontFixture::create();

    $this->get(route('front.home'))
        ->assertHeader('Vary', 'Accept')
        ->assertHeader('Link', '<'.route('front.sitemap').'>; rel="sitemap"; type="application/xml", <'.route('front.llms').'>; rel="describedby"; type="text/plain"');
});

it('describes the business in JSON-LD without ratings or the street address', function () {
    FrontFixture::create();

    $html = $this->get(route('front.page', 'services'))->getContent();
    [$business, $service] = frontJsonLd($html);

    expect($business)->toMatchArray([
        '@context' => 'https://schema.org',
        '@type' => 'LocalBusiness',
        'name' => 'Acme Cleaning',
        'telephone' => '+1 239-555-0100',
        'areaServed' => [['@type' => 'City', 'name' => 'Naples'], ['@type' => 'City', 'name' => 'Estero']],
        'address' => ['@type' => 'PostalAddress', 'addressLocality' => 'Naples', 'addressRegion' => 'FL', 'addressCountry' => 'US'],
        'openingHours' => ['Mo-Fr 08:00-18:00'],
    ])->not->toHaveKey('aggregateRating')
        ->and($service)->toMatchArray(['@type' => 'Service', 'name' => 'House cleaning', 'provider' => ['@id' => route('front.home').'#business']])
        ->and($html)->not->toContain('100 Example Ave')
        ->and($html)->not->toContain('34102');
});

it('publishes the street address when facts allow it', function () {
    $facts = FrontFixture::defaultFacts();
    $facts['address']['publish'] = true;

    FrontFixture::create(['facts.yaml' => FrontFixture::facts(['address' => $facts['address']])]);

    $response = $this->get(route('front.home'));

    expect(frontJsonLd($response->getContent())[0]['address'])->toMatchArray(['streetAddress' => '100 Example Ave', 'postalCode' => '34102']);
    $response->assertSee('<li>100 Example Ave, Naples, FL, 34102, US</li>', false);
});

it('drops rating claims from a page JSON-LD block', function () {
    FrontFixture::create([
        'pages/services.en.md' => FrontFixture::page(
            ['title' => 'Cleaning services', 'description' => 'House cleaning.', 'jsonld' => ['@type' => 'FAQPage', 'name' => 'Questions', 'aggregateRating' => ['ratingValue' => 5]]],
            '# Cleaning services',
        ),
    ]);

    $blocks = frontJsonLd($this->get(route('front.page', 'services'))->getContent());

    expect(end($blocks))->toBe(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'name' => 'Questions']);
});

it('returns 404 for pages and languages the front does not have', function (string $path) {
    FrontFixture::create();

    $this->get($path)->assertNotFound();
})->with([
    'unknown page' => '/site/missing',
    'unknown page in a language' => '/site/es/missing',
    'unknown language' => '/site/fr',
    'page in an unknown language' => '/site/fr/services',
    'slug with a dot' => '/site/services.en',
]);

it('redirects duplicate URLs to the canonical one, keeping the query', function (string $path, string $canonical) {
    FrontFixture::create();

    $this->get($path)
        ->assertMovedPermanently()
        ->assertRedirect($canonical);
})->with([
    'default language home' => ['/site/en', '/site'],
    'default language page' => ['/site/en/services?s=sig&e=99', '/site/services?e=99&s=sig'],
    'home slug' => ['/site/home', '/site'],
    'home slug in a language' => ['/site/es/home', '/site/es'],
]);

it('strips raw HTML and unsafe links from page bodies', function () {
    FrontFixture::create([
        'pages/services.en.md' => FrontFixture::page(
            ['title' => 'Cleaning services', 'description' => 'House cleaning.'],
            "# Cleaning services\n\n<script>alert('body')</script>\n\n<img src=x onerror=alert(1)>\n\n[Book now](javascript:alert(1))",
        ),
    ]);

    $this->get(route('front.page', 'services'))
        ->assertSee('Book now')
        ->assertDontSee("<script>alert('body')</script>", false)
        ->assertDontSee('onerror', false)
        ->assertDontSee('javascript:', false);
});

it('escapes facts and keeps theme tokens inside their style element', function () {
    FrontFixture::create([
        'facts.yaml' => FrontFixture::facts(['name' => 'Acme <script>alert(1)</script>']),
        'theme/tokens.css' => ":root { --color-accent: #123456; }\n</style><script>alert(2)</script>",
    ]);

    $this->get(route('front.home'))
        ->assertSee('Acme &lt;script&gt;alert(1)&lt;/script&gt;', false)
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertDontSee('</style><script>alert(2)', false)
        ->assertSee('--color-accent: #123456;', false);
});

it('sends X-Robots-Tag noindex with both the HTML and the markdown of a noindex page', function (string $accept, string $contentType) {
    FrontFixture::create([
        'pages/thanks.en.md' => FrontFixture::page(['title' => 'Thanks', 'description' => 'Thanks for your request.', 'noindex' => true], '# Thanks'),
    ]);

    $this->get(route('front.page', 'thanks'), ['Accept' => $accept])
        ->assertHeader('Content-Type', $contentType)
        ->assertHeader('X-Robots-Tag', 'noindex');
})->with([
    'html' => ['text/html', 'text/html; charset=utf-8'],
    'markdown' => ['text/markdown', 'text/markdown; charset=UTF-8'],
]);

it('does not mark the live front noindex', function () {
    FrontFixture::create();

    $this->get(route('front.home'))->assertHeaderMissing('X-Robots-Tag');
});

it('never lets search engines index the bundled demo company', function () {
    config(['agents.company' => 'demo']);

    $this->get(route('front.home'))
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});

it('serves the front at the root of the configured front host', function () {
    $_SERVER['AGENTS_FRONT_HOST'] = $_ENV['AGENTS_FRONT_HOST'] = 'front.test';

    try {
        $this->refreshApplication();
        FrontFixture::create();

        $this->get('http://front.test/')
            ->assertSee('<h1>Clean homes in Naples</h1>', false)
            ->assertSee('<link rel="canonical" href="http://front.test">', false);
        $this->get('http://front.test/es/services')->assertSee('<h1>Servicios de limpieza</h1>', false);
        $this->get('http://front.test/robots.txt')->assertSee('Sitemap: http://front.test/sitemap.xml');
    } finally {
        unset($_SERVER['AGENTS_FRONT_HOST'], $_ENV['AGENTS_FRONT_HOST']);
    }
});
