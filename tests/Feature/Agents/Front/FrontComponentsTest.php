<?php

use App\Core\Front\FrontKit;
use App\Core\Front\FrontSite;
use Illuminate\Support\Str;
use Tests\Feature\Agents\Front\FrontFixture;

afterEach(fn () => FrontFixture::cleanup());

/**
 * A front with a few assets and a declared form, and the FrontSite that renders it.
 *
 * @param  array<string, string|null>  $files
 */
function componentSite(array $files = []): FrontSite
{
    FrontFixture::create([
        'facts.yaml' => FrontFixture::facts([
            'forms' => [
                ['slug' => 'contact', 'name' => 'Contact us', 'status' => 'live', 'client' => 'example-co', 'form' => ['en' => 'contact-en', 'es' => 'contact-es'], 'hosted' => 'https://example-co.aiff.dev/contact'],
                ['slug' => 'quote', 'name' => 'Get a quote', 'status' => 'draft', 'hosted' => 'https://example-co.aiff.dev/quote'],
            ],
        ]),
        'assets/shot.png' => base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAIAAAADCAIAAAA2iEnWAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAC0lEQVQImWNgwAIAABUAAbJuDnMAAAAASUVORK5CYII='),
        'assets/icons/spark.svg' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/></svg>',
        ...$files,
    ]);

    return new FrontSite(app(FrontKit::class));
}

function renderBody(string $body, string $lang = 'en'): string
{
    return componentSite()->components()->render($body, $lang);
}

it('renders a section with kicker, image and nested cards', function () {
    $html = renderBody(<<<'MD'
        :::section variant="muted" class="brand-band" kicker="What we do" id="services" image="assets/shot.png" alt="A screenshot"
        ## Services

        :::cards
        :::card title="Websites" icon="code"
        Fast sites.
        :::
        :::card-grid
        :::
        :::
        :::
        MD);

    expect($html)
        ->toContain('<section class="front-section front-section--muted brand-band front-section--split" id="services" data-reveal>')
        ->toContain('<p class="front-kicker">What we do</p>')
        ->toContain('<h2>Services</h2>')
        ->toMatch('#<img src="http://localhost/site/assets/shot\.png\?v=[0-9a-f]+" alt="A screenshot" width="2" height="3"#')
        ->toContain('<div class="front-cards" data-reveal>')
        ->toContain('<h3 class="front-card__title">')
        ->toContain('Websites')
        ->toContain('<p>Fast sites.</p>')
        ->toContain('<span class="front-icon"><svg');
});

it('renders card chips, a second image, a button and an asset icon', function () {
    $html = renderBody(<<<'MD'
        :::card title="Example App" chips="Live|Built by Example Co" chip_variant="live" image="assets/shot.png" alt="One" image2="assets/shot.png" alt2="Two" href="https://app.example.com" button_label="Visit Example App" icon="asset:icons/spark.svg"
        Copy.
        :::
        MD);

    expect($html)
        ->toContain('<li class="front-chip front-chip--live">Live</li>')
        ->toContain('<li class="front-chip front-chip--live">Built by Example Co</li>')
        ->toContain('alt="One"')
        ->toContain('class="front-card__image front-card__image--second"')
        ->toContain('alt="Two"')
        ->toContain('<a class="btn btn-primary" href="https://app.example.com">Visit Example App</a>')
        ->toMatch('#<span class="front-icon"><img src="http://localhost/site/assets/icons/spark\.svg\?v=[0-9a-f]+" alt=""#');
});

it('renders stat, faq, cta, quote, steps, flow, proof and timeline', function () {
    $html = renderBody(<<<'MD'
        :::stat value="12" label="Sites live"
        :::

        :::accordion
        :::item question="Do you host?"
        Yes.
        :::
        :::

        :::cta-band title="Ready?" href="services" label="See services" secondary_href="mailto:hi@example.com" secondary_label="Email"
        Let's talk.
        :::

        :::testimonial author="Sam" role="Owner"
        It works.
        :::

        :::steps
        :::step title="Call" icon="phone"
        We listen.
        :::
        :::step title="Build" time="Week 2"
        :::
        :::

        :::flow label="How it works"
        :::step title="Website" icon="globe"
        :::
        :::step title="Assistant" icon="robot" chip="Built by Example Co"
        :::
        :::

        :::proof href="services" label="More"
        :::card title="Fast" icon="lightning"
        Quick pages.
        :::
        :::

        :::timeline title="Launch" where="Online" span="Two weeks" note="Typical."
        :::stat value="3" label="Pages"
        :::
        :::step time="Day 1" title="Kickoff" status="done"
        :::
        :::
        MD);

    expect($html)
        ->toContain('<p class="front-stat__value">12</p>')
        ->toContain('<p class="front-stat__label">Sites live</p>')
        ->toContain('<div class="front-faq">')
        ->toContain('<summary class="front-item__question">Do you host?')
        ->toContain('<aside class="front-cta" data-reveal>')
        ->toContain('<a class="btn btn-primary" href="http://localhost/site/services">See services</a>')
        ->toContain('<a class="btn btn-outline" href="mailto:hi@example.com">Email</a>')
        ->toContain('<blockquote class="front-quote__text"><p>It works.</p>')
        ->toContain('<cite>Sam</cite>')
        ->toContain('<span class="front-step__number" aria-hidden="true">2</span>')
        ->toContain('<p class="front-step__time">Week 2</p>')
        ->toContain('<ol class="front-flow__list" aria-label="How it works">')
        ->toContain('<li class="front-flow__arrow" aria-hidden="true">')
        ->toContain('<p class="front-chip front-step__chip">Built by Example Co</p>')
        ->toContain('<h3 class="front-proof__title">Fast</h3>')
        ->toContain('<p class="front-proof__more"><a href="http://localhost/site/services">More</a></p>')
        ->toContain('<h3 class="front-timeline__title">Launch</h3>')
        ->toContain('<dd class="front-stat__value">3</dd>')
        ->toContain('<span class="front-chip front-timeline__status">done</span>')
        ->toContain('<p class="front-timeline__note">Typical.</p>');

    expect(substr_count($html, 'front-flow__arrow'))->toBe(1);
});

it('places a declared form inline: the live embed, or the hosted link for a draft', function () {
    $site = componentSite();

    $live = $site->components()->render(":::form slug=\"contact\" class=\"wide\"\nWe reply fast.\n:::\n", 'es');
    $draft = $site->components()->render(":::form slug=\"quote\"\n:::\n", 'en');
    $unknown = $site->components()->render(":::form slug=\"nope\"\n:::\n", 'en');

    expect($live)
        ->toContain('<div class="front-form wide" id="form-contact"')
        ->toContain('data-aiformflow data-client="example-co" data-form="contact-es" data-theme="light" data-lang="es"')
        ->toContain('<p>We reply fast.</p>')
        ->toContain('<a href="https://example-co.aiff.dev/contact">')
        ->and($draft)->not->toContain('data-aiformflow')
        ->toContain('<a class="btn btn-primary" href="https://example-co.aiff.dev/quote">Open the form</a>')
        ->and($unknown)->toBe('');
});

it('escapes attribute text and drops unsafe links, classes, ids, icons and assets', function (string $body, array $absent, array $present) {
    $html = renderBody($body);

    foreach ($absent as $needle) {
        expect($html)->not->toContain($needle);
    }

    foreach ($present as $needle) {
        expect($html)->toContain($needle);
    }
})->with([
    'script in a title' => [
        ":::card title='\"><script>alert(1)</script>'\nx\n:::\n",
        ['<script>', '"><'],
        ['&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;'],
    ],
    'encoded script in a title' => [
        ":::card title=\"&lt;img src=x onerror=alert(1)&gt;\"\nx\n:::\n",
        ['<img src=x'],
        ['&lt;img src=x onerror=alert(1)&gt;'],
    ],
    'javascript: href' => [
        ":::cta title=\"Go\" href=\"javascript:alert(1)\" label=\"Click\"\n:::\n",
        ['javascript:', 'Click</a>'],
        ['<h2 class="front-cta__title">Go</h2>'],
    ],
    'data: and protocol-relative hrefs' => [
        ":::card title=\"A\" href=\"data:text/html,x\" button_label=\"B\"\n:::\n:::card title=\"C\" href=\"//evil.example\"\n:::\n",
        ['data:text', '//evil.example', '>B</a>'],
        ['C'],
    ],
    'quote-breaking href' => [
        ":::card title=\"A\" href='https://example.com/\"onmouseover=alert(1)' button_label=\"B\"\n:::\n",
        ['onmouseover=alert(1)"', '"onmouseover'],
        ['A'],
    ],
    'class, variant and id injection' => [
        ":::section class='x\" onclick=\"alert(1)' variant=\"a<b\" id='\"><script>'\nText\n:::\n",
        ['onclick', 'a<b', '<script>', 'front-section--a'],
        ['<section class="front-section"'],
    ],
    'traversal and foreign images' => [
        ":::section image=\"assets/../../.env\"\n:::\n:::card image=\"https://evil.example/x.png\" title=\"T\"\n:::\n:::card image=\"/etc/passwd\" title=\"U\"\n:::\n",
        ['.env', 'evil.example', 'passwd', '<img'],
        ['T', 'U'],
    ],
    'unknown icon and non-image asset icon' => [
        ":::card title=\"A\" icon=\"<svg onload=alert(1)>\"\n:::\n:::card title=\"B\" icon=\"asset:../facts.yaml\"\n:::\n",
        ['onload', 'facts.yaml', 'front-icon'],
        ['A', 'B'],
    ],
    'raw html inside a component' => [
        ":::section\n<script>alert(1)</script><iframe src=\"https://evil.example\"></iframe>\n\n[x](javascript:alert(1))\n:::\n",
        ['<script>', '<iframe', 'javascript:'],
        ['<section class="front-section"'],
    ],
    'style attribute' => [
        ":::card title=\"A\" style=\"background:url(https://evil.example)\"\n:::\n",
        ['style=', 'evil.example'],
        ['A'],
    ],
]);

it('shows unknown and unclosed fences as plain text', function () {
    $html = renderBody(":::script src=\"https://evil.example/x.js\"\nalert(1)\n:::\n\n:::card title=\"Open\"\nNever closed.\n");

    expect($html)
        ->not->toContain('<script')
        ->not->toContain('front-card')
        ->toContain(':::script src=')
        ->toContain('alert(1)')
        ->toContain(':::card title=')
        ->toContain('Never closed.');
});

it('leaves a body without fences exactly as plain markdown renders it', function () {
    $site = componentSite();
    $body = "# Title\n\nSome *text* with [a page](services) and <b>raw</b> html.\n\n- one\n- two";

    expect($site->bodyHtml(['slug' => 'home', 'lang' => 'en', 'body' => $body]))->toBe(
        str_replace('href="services"', 'href="http://localhost/site/services"', Str::markdown($body, ['html_input' => 'strip', 'allow_unsafe_links' => false])),
    );
});

it('writes every component as readable markdown without fence lines', function () {
    $site = componentSite();
    $markdown = $site->components()->toMarkdown(<<<'MD'
        Intro.

        :::section kicker="What we do"
        ## Services

        :::cards
        :::card title="Websites" href="services"
        Fast sites.
        :::
        :::card title="Example App" chips="Live|Beta" image="assets/shot.png" alt="Shot" href="https://app.example.com" button_label="Visit"
        An app.
        :::
        :::
        :::

        :::faq
        :::item question="Do you host?"
        Yes.
        :::
        :::

        :::steps
        :::step title="Call"
        We listen.
        :::
        :::step title="Build"
        :::
        :::

        :::flow
        :::step title="Website" chip="Ours"
        :::
        :::

        :::cta title="Ready?" href="services" label="See services"
        Let's talk.
        :::

        :::quote author="Sam" role="Owner"
        It works.
        :::

        :::stat value="12" label="Sites"
        :::

        :::form slug="quote"
        :::

        :::mystery
        Kept as text.
        :::
        MD, 'en');

    expect($markdown)
        ->not->toMatch('/^:::/m')
        ->toContain("Intro.\n\n*What we do*\n\n## Services")
        ->toContain('### [Websites](http://localhost/site/services)')
        ->toContain("### Example App\n\n*Live · Beta*\n\nAn app.")
        ->toMatch('#!\[Shot\]\(http://localhost/site/assets/shot\.png\?v=[0-9a-f]+\)#')
        ->toContain('[Visit](https://app.example.com)')
        ->toContain("### Do you host?\n\nYes.")
        ->toContain("1. **Call**\n   We listen.\n2. **Build**")
        ->toContain('1. **Website** (Ours)')
        ->toContain("## Ready?\n\nLet's talk.\n\n[See services](http://localhost/site/services)")
        ->toContain("> It works.\n\n> — Sam, Owner")
        ->toContain('**12** Sites')
        ->toContain('### Get a quote')
        ->toContain('[Open the form](https://example-co.aiff.dev/quote)')
        ->toContain('Kept as text.');
});

it('lists the forms a body places and the unknown names and attributes it uses', function () {
    $components = componentSite()->components();
    $body = ":::form slug=\"contact\"\n:::\n:::section kicker=\"A\" colour=\"red\"\n:::form slug=\"quote\"\n:::\n:::\n:::marquee\n:::\n";

    expect($components->formSlugs($body))->toBe(['contact', 'quote'])
        ->and($components->unknownNames($body))->toBe(['marquee'])
        ->and($components->unknownAttributes($body))->toBe([['section', 'colour']]);
});
