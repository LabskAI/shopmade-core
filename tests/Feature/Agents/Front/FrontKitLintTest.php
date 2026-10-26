<?php

use App\Core\Front\FrontLinter;
use Tests\Feature\Agents\Front\FrontFixture;

afterEach(fn () => FrontFixture::cleanup());

/**
 * Lints the acme fixture with one extra page and these facts, returning "rule: message" lines per level.
 *
 * @param  array<string, mixed>  $facts
 * @param  array<string, string|null>  $files
 * @return array{errors: list<string>, warnings: list<string>}
 */
function lintKit(array $facts = [], array $files = []): array
{
    FrontFixture::create(['facts.yaml' => FrontFixture::facts($facts), ...$files]);
    $report = app(FrontLinter::class)->lint('acme');
    $lines = fn (array $issues): array => array_map(fn (array $issue): string => $issue['rule'].': '.$issue['message'], $issues);

    return ['errors' => $lines($report['errors']), 'warnings' => $lines($report['warnings'])];
}

function kitPage(string $body, array $frontmatter = []): string
{
    return FrontFixture::page(['title' => 'About', 'description' => 'Who we are.', ...$frontmatter], $body);
}

it('warns about unknown component fences and attributes', function () {
    $report = lintKit(files: ['pages/about.en.md' => kitPage(":::marquee\nHi\n:::\n\n:::card title=\"A\" colour=\"red\"\n:::\n")]);

    expect($report['errors'])->toBe([])
        ->and($report['warnings'])->toContain(
            'components: Unknown component ":::marquee" is shown as plain text. Components: section, cards, card-grid, card, stat, faq, accordion, item, cta, cta-band, quote, testimonial, steps, step, proof, timeline, flow, form.',
        )
        ->and(implode("\n", $report['warnings']))->toContain('components: Component ":::card" does not take "colour"');
});

it('checks facts.forms and warns about forms a page names but facts do not declare', function () {
    $report = lintKit(
        ['forms' => [
            ['slug' => 'contact', 'status' => 'live', 'client' => 'example-co'],
            ['slug' => 'Bad Slug'],
            ['slug' => 'quote', 'status' => 'published', 'hosted' => 'http://example.com/quote', 'form' => 'a b'],
        ]],
        ['pages/about.en.md' => kitPage(":::form slug=\"missing\"\n:::\n", ['forms' => ['contact', 'other']])],
    );

    expect($report['errors'])->toContain(
        'facts: facts.forms[1] needs a lowercase slug such as "contact".',
        'facts: facts.forms[2].status must be live, draft or off (found "published").',
        'facts: facts.forms[2].hosted must be an https URL.',
        'facts: facts.forms[2].form must be an AIFormFlow form id (letters, digits, - and _), or one per language.',
    )
        ->and($report['warnings'])->toContain(
            'forms: Form "other" is not a slug in facts.forms (contact, quote), so it is not shown.',
            'forms: Form "missing" is not a slug in facts.forms (contact, quote), so it is not shown.',
        );
});

it('reports hero, component and markdown assets that are not served files', function () {
    $report = lintKit(files: [
        'assets/ok.svg' => '<svg xmlns="http://www.w3.org/2000/svg"/>',
        'pages/about.en.md' => kitPage(
            ":::section image=\"assets/missing.png\"\n:::\n:::card icon=\"asset:icons/nope.svg\" title=\"A\"\n:::\n\n![x](assets/gone.webp) ![y](assets/ok.svg)\n",
            ['hero' => ['image' => 'assets/ok.svg', 'poster' => 'assets/../facts.yaml', 'position' => 'center; color: red']],
        ),
    ]);

    expect($report['errors'])->toContain(
        'assets: Frontmatter hero.poster "assets/../facts.yaml" is not a file under assets/.',
        'assets: Asset "assets/missing.png" in section image is not a file under assets/ (or not a served type).',
        'assets: Asset "asset:icons/nope.svg" in card icon is not a file under assets/ (or not a served type).',
        'assets: Asset "assets/gone.webp" in a markdown link is not a file under assets/ (or not a served type).',
    )
        ->and(implode("\n", $report['errors']))->not->toContain('ok.svg')
        ->and($report['warnings'])->toContain('hero: Frontmatter hero.position "center; color: red" is ignored: use "x% y%", e.g. "50% 30%".');
});

it('allows only company scripts and AIFormFlow URLs, and woff2 font faces that exist', function () {
    $report = lintKit([
        'scripts' => [['src' => 'assets/motion.js'], ['src' => 'https://cdn.example.com/x.js'], ['src' => 'https://aiformflow.com/x.js'], ['src' => 'assets/missing.js']],
        'fonts' => ['faces' => [['family' => 'Sans', 'src' => 'assets/sans.woff2'], ['family' => 'Serif', 'src' => 'assets/serif.ttf']]],
    ], ['assets/motion.js' => '1;']);

    expect($report['errors'])->toBe([
        'facts: facts.scripts[1].src "https://cdn.example.com/x.js" is not allowed: only assets/….js files and https://aiformflow.com URLs load.',
        'facts: facts.fonts.faces[1] needs a family and a src .woff2 file under assets/.',
        'assets: facts.scripts[3].src "assets/missing.js" is not a file under assets/.',
        'assets: facts.fonts.faces[0].src "assets/sans.woff2" is not a file under assets/.',
        'assets: facts.fonts.faces[1].src "assets/serif.ttf" is not a file under assets/.',
    ]);
});

it('rejects component CSS that could escape its style element or load from outside assets/', function (string $css) {
    $report = lintKit(files: ['theme/components.css' => $css]);

    expect(implode("\n", $report['errors']))->toContain('components_css: ');
})->with([
    'closing style tag' => ['.a { color: red; } </style><script>alert(1)</script>'],
    'remote url' => ['.a { background: url(https://evil.example/x.png); }'],
    'import' => ['@import "https://evil.example/x.css";'],
    'escape' => ['.a { background: u\\72l(x); }'],
    'expression' => ['.a { width: expression(alert(1)); }'],
    'image-set' => ['.a { background-image: image-set("https://evil.example/x.png" 1x); }'],
]);
