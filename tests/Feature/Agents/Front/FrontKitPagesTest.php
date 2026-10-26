<?php

use Tests\Feature\Agents\Front\FrontFixture;

afterEach(fn () => FrontFixture::cleanup());

/**
 * A front that uses every kit feature: hero art, components, an inline and an after-article form, component CSS,
 * a company script, a self-hosted font, a logo, a header CTA, footer links and four languages.
 *
 * @param  array<string, mixed>  $facts
 * @param  array<string, string|null>  $files
 */
function kitFront(array $facts = [], array $files = []): string
{
    $page = fn (string $lang, string $title, string $body, array $extra = []): string => FrontFixture::page(
        ['title' => $title, 'description' => 'Software for small shops.', 'layout' => 'home', 'hero' => ['image' => 'assets/hero-art.svg', 'alt' => '', 'kicker' => 'Example Co'], 'forms' => ['contact'], ...$extra],
        $body,
    );
    $body = "# We build software\n\nSmall tools that work.\n\n:::section kicker=\"Services\"\n## What we do\n\n:::cards\n:::card title=\"Websites\" icon=\"code\"\nFast sites.\n:::\n:::\n:::\n";

    return FrontFixture::create([
        'facts.yaml' => FrontFixture::facts([
            'name' => 'Example Co',
            'legal_name' => 'Example Co LLC',
            'languages' => ['en', 'es', 'pt', 'fr'],
            'logo' => ['src' => 'assets/logo.svg', 'alt' => 'Example Co'],
            'cta' => ['label' => ['en' => 'Book a call', 'pt' => 'Agendar'], 'target' => '#form-contact'],
            'forms' => [['slug' => 'contact', 'name' => 'Contact', 'status' => 'live', 'client' => 'example-co', 'form' => 'contact', 'hosted' => 'https://example-co.aiff.dev/contact']],
            'scripts' => [['src' => 'assets/motion.js'], ['src' => 'https://evil.example/x.js'], ['src' => 'assets/off.js', 'enabled' => false]],
            'fonts' => ['faces' => [['family' => 'Example Sans', 'src' => 'assets/fonts/example.woff2', 'weight' => '400 700', 'preload' => true]]],
            'footer' => ['legal' => [['label' => 'Privacy', 'slug' => 'services']], 'columns' => [['title' => 'Company', 'links' => [['label' => 'Docs', 'href' => 'https://docs.example.com']]]]],
            ...$facts,
        ]),
        'pages/home.en.md' => $page('en', 'Example Co', $body),
        'pages/home.es.md' => $page('es', 'Example Co', $body),
        'pages/home.pt.md' => $page('pt', 'Example Co', $body),
        'pages/home.fr.md' => $page('fr', 'Example Co', $body),
        'lang/pt.yaml' => "language_name: Português\nopen_form: Abrir o formulário\n",
        'lang/fr.yaml' => "language_name: Français\nprefer_to_talk: Vous préférez parler ?\n",
        'assets/hero-art.svg' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 900"><rect width="1200" height="900"/></svg>',
        'assets/logo.svg' => '<svg xmlns="http://www.w3.org/2000/svg" width="180" height="32" viewBox="0 0 90 16"/>',
        'assets/motion.js' => 'document.documentElement.classList.add("js");',
        'assets/off.js' => 'alert(1);',
        'assets/fonts/example.woff2' => 'wOF2font',
        'theme/components.css' => ".front-card { border-radius: 12px; background: url(\"assets/hero-art.svg\"); }\n.front-cta { background: url(https://evil.example/x.png); }\n@import url(\"https://evil.example/x.css\");\n",
        ...$files,
    ]);
}

it('renders hero art, components, logo, CTA, fonts, component CSS and scripts with a CSP nonce', function () {
    kitFront();

    $response = $this->get(route('front.home'))->assertOk();
    $csp = (string) $response->headers->get('Content-Security-Policy');
    preg_match("/'nonce-([A-Za-z0-9+\\/=]+)'/", $csp, $nonce);
    $html = $response->getContent();

    expect($nonce)->toHaveKey(1)
        ->and($csp)->toContain("default-src 'self'")
        ->toContain("script-src 'self' 'nonce-{$nonce[1]}' https://aiformflow.com https://*.aiformflow.com")
        ->toContain('frame-src https://aiformflow.com https://*.aiformflow.com')
        ->toContain("connect-src 'self' https://aiformflow.com https://*.aiformflow.com")
        ->toContain("object-src 'none'")
        ->and($html)
        ->toMatch('#<img class="front-hero__image" src="http://localhost/site/assets/hero-art\.svg\?v=[0-9a-f]+" alt="" width="1200" height="900"#')
        ->toContain('<p class="front-kicker">Example Co</p>')
        ->toContain('<h1>We build software</h1>')
        ->toContain('<div class="front-cards" data-reveal>')
        ->toMatch('#<img class="brand-logo" src="http://localhost/site/assets/logo\.svg\?v=[0-9a-f]+" alt="Example Co" width="180" height="32">#')
        ->toContain('<a class="btn btn-primary header-cta" href="#form-contact">Book a call</a>')
        ->toMatch('#<link rel="preload" href="http://localhost/site/assets/fonts/example\.woff2\?v=[0-9a-f]+" as="font"#')
        ->toContain('font-family: "Example Sans"')
        ->toContain('font-weight: 400 700;')
        ->toMatch('#\.front-card \{ border-radius: 12px; background: url\("http://localhost/site/assets/hero-art\.svg\?v=[0-9a-f]+"\); \}#')
        ->toContain('.front-cta { background: none; }')
        ->not->toContain('evil.example')
        ->not->toContain('off.js')
        ->toMatch('#<script src="http://localhost/site/assets/motion\.js\?v=[0-9a-f]+" defer\s+nonce="'.preg_quote($nonce[1], '#').'"></script>#')
        ->toContain('<script src="https://aiformflow.com/embed/v1/form.js" defer  nonce="'.$nonce[1].'"></script>')
        ->toContain('<a href="https://docs.example.com">Docs</a>')
        ->toContain('<li><a href="http://localhost/site/services">Privacy</a></li>');

    preg_match_all('/<script\b(?![^>]*application\/ld\+json)[^>]*>/', $html, $scripts);

    foreach ($scripts[0] as $tag) {
        expect($tag)->toContain('nonce="'.$nonce[1].'"');
    }
});

it('labels the language switcher with language_name and uses the pt and fr strings', function () {
    kitFront();

    $this->get(route('front.language', 'pt'))
        ->assertSee('<html lang="pt">', false)
        ->assertSee('<a href="http://localhost/site/fr" hreflang="fr" lang="fr">Français</a>', false)
        ->assertSee('aria-current="true">Português</a>', false)
        ->assertSee('<a class="btn btn-primary header-cta" href="#form-contact">Agendar</a>', false)
        ->assertSee('data-lang="pt"', false)
        ->assertSee('Abrir o formulário');

    $this->get(route('front.language', 'fr'))->assertSee('Vous préférez parler ?');
});

it('embeds a form only when its status is live, and loads form.js only then', function (?string $status, bool $embeds) {
    kitFront(['forms' => [['slug' => 'contact', 'name' => 'Contact', 'status' => $status, 'client' => 'example-co', 'hosted' => 'https://example-co.aiff.dev/contact']]]);

    $html = $this->get(route('front.home'))->assertOk()->getContent();

    expect(str_contains($html, 'data-aiformflow'))->toBe($embeds)
        ->and(str_contains($html, 'aiformflow.com/embed/v1/form.js'))->toBe($embeds)
        ->and($html)->toContain('id="form-contact"')
        ->toContain('href="https://example-co.aiff.dev/contact"');
})->with([
    'live' => ['live', true],
    'draft' => ['draft', false],
    'off' => ['off', false],
    'no status' => [null, false],
]);

it('shows a form placed inline once, not again after the article', function () {
    kitFront(files: [
        'pages/home.en.md' => FrontFixture::page(
            ['title' => 'Example Co', 'description' => 'Software.', 'forms' => ['contact']],
            "# Contact\n\n:::section kicker=\"Talk to us\"\n:::form slug=\"contact\"\n:::\n:::\n",
        ),
    ]);

    $html = $this->get(route('front.home'))->getContent();

    expect(substr_count($html, 'id="form-contact"'))->toBe(1)
        ->and($html)->not->toContain('class="front-forms"')
        ->toContain('aiformflow.com/embed/v1/form.js');
});

it('points the header CTA at the page that shows the form', function () {
    kitFront(files: [
        'pages/services.en.md' => FrontFixture::page(['title' => 'Services', 'description' => 'What we do.'], '# Services'),
    ]);

    $this->get(route('front.page', 'services'))
        ->assertSee('<a class="btn btn-primary header-cta" href="http://localhost/site#form-contact">Book a call</a>', false)
        ->assertDontSee('form.js');
});

it('keeps a page without kit features rendering as before, with no script', function () {
    FrontFixture::create();

    $html = $this->get(route('front.home'))->assertOk()->getContent();

    expect($html)->not->toMatch('/<script\b(?![^>]*application\/ld\+json)/')
        ->not->toContain('class="wrap front-hero')
        ->toContain('<a class="call" href="tel:');
});

it('serves components as markdown to Accept: text/markdown and in llms-full.txt, with no fence lines', function () {
    kitFront();

    $markdown = $this->get(route('front.home'), ['Accept' => 'text/markdown'])->assertOk()->getContent();
    $full = $this->get(route('front.llms-full'))->assertOk()->getContent();

    expect($markdown)->toContain("*Services*\n\n## What we do\n\n### Websites\n\nFast sites.")
        ->not->toMatch('/^:::/m')
        ->and($full)->toContain("### Websites\n\nFast sites.")
        ->not->toMatch('/^:::/m');
});

it('lints the unsafe component CSS and the script from a foreign host as errors', function () {
    kitFront();

    $this->artisan('front:lint', ['company' => 'acme'])
        ->expectsOutputToContain('components.css may not contain @import')
        ->expectsOutputToContain('facts.scripts[1].src "https://evil.example/x.js" is not allowed')
        ->expectsOutputToContain('2 error(s)')
        ->assertExitCode(1);
});
