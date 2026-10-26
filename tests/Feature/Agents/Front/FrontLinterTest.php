<?php

use App\Core\Front\FrontDiscovery;
use App\Core\Front\FrontLinter;
use Symfony\Component\Yaml\Yaml;
use Tests\Feature\Agents\Front\FrontFixture;

afterEach(fn () => FrontFixture::cleanup());

function lintAcme(): array
{
    return app(FrontLinter::class)->lint('acme');
}

/**
 * @param  array<string, mixed>  $frontmatter
 */
function acmePage(array $frontmatter = [], string $body = "# Cleaning\n\nWe clean houses."): string
{
    return FrontFixture::page([
        'title' => 'Cleaning services',
        'description' => 'House cleaning and office cleaning.',
        ...$frontmatter,
    ], $body);
}

it('passes a front that follows every rule', function () {
    FrontFixture::create();

    expect(lintAcme())->toBe(['ok' => true, 'errors' => [], 'warnings' => []]);
});

it('reports a never-write phrase with the file and the sentence it appears in', function (string $file, string $contents, string $where) {
    FrontFixture::create([$file => $contents]);

    $report = lintAcme();

    expect($report['ok'])->toBeFalse()
        ->and($report['errors'])->toContain([
            'rule' => 'never_write',
            'file' => 'fronts/acme/'.$file,
            'message' => 'Never-write phrase "OSHA" in '.$where.': "We are OSHA trained"',
        ]);
})->with([
    'page body' => ['pages/services.en.md', acmePage([], "# Cleaning\n\nWe are OSHA trained"), 'body'],
    'frontmatter' => ['pages/services.en.md', acmePage(['description' => 'We are OSHA trained']), 'frontmatter description'],
    'nested frontmatter' => ['pages/services.en.md', acmePage(['jsonld' => ['@type' => 'Service', 'slogan' => 'We are OSHA trained']]), 'frontmatter jsonld.slogan'],
    'UI string' => ['lang/en.yaml', "call: We are OSHA trained\n", 'string call'],
]);

it('matches never-write phrases case-insensitively and only as whole words', function () {
    FrontFixture::create([
        'pages/services.en.md' => acmePage([], "# Cleaning\n\nWe keep your best interests in mind, and #10 on the list is windows.\n\nWe are BEST IN\nRiverton."),
    ]);

    $messages = array_column(lintAcme()['errors'], 'message');

    expect($messages)->toBe(['Never-write phrase "best in" in body: "We are BEST IN"']);
});

it('rejects cities and services that are not in the facts', function () {
    FrontFixture::create([
        'pages/services.en.md' => acmePage(['cities' => ['Riverton', 'Pine Ridge'], 'service' => 'pool-cleaning']),
    ]);

    $errors = lintAcme()['errors'];

    expect($errors)->toContain([
        'rule' => 'cities',
        'file' => 'fronts/acme/pages/services.en.md',
        'message' => 'Frontmatter cities "Pine Ridge" is not in facts.cities (Riverton, Oak Hollow).',
    ])->toContain([
        'rule' => 'services',
        'file' => 'fronts/acme/pages/services.en.md',
        'message' => 'Frontmatter service "pool-cleaning" is not a slug in facts.services (house-cleaning, office-cleaning).',
    ])->toHaveCount(2);
});

it('rejects currency amounts in pages', function (string $text, string $amount) {
    FrontFixture::create(['pages/services.en.md' => acmePage([], "# Cleaning\n\n".$text)]);

    $errors = lintAcme()['errors'];

    expect($errors)->toHaveCount(1)
        ->and($errors[0]['rule'])->toBe('currency')
        ->and($errors[0]['message'])->toStartWith('Currency amount "'.$amount.'" in body');
})->with([
    'dollar sign' => ['A deep clean is $120.', '$120'],
    'currency code first' => ['From USD 50 per visit.', 'USD 50'],
    'currency code after' => ['Only 50 USD per visit.', '50 USD'],
    'euro sign' => ['Ask about our € pricing.', '€'],
    'spelled out' => ['A few dollars a week.', 'dollars'],
]);

it('requires a title and a description on every page', function () {
    FrontFixture::create(['pages/services.en.md' => FrontFixture::page(['nav_label' => 'Services'], '# Cleaning')]);

    expect(array_column(lintAcme()['errors'], 'message'))->toBe([
        'Frontmatter "title" is required.',
        'Frontmatter "description" is required.',
    ]);
});

it('warns when a page is missing in one language', function () {
    FrontFixture::create(['pages/services.es.md' => null]);

    $report = lintAcme();

    expect($report['ok'])->toBeTrue()
        ->and($report['warnings'])->toBe([[
            'rule' => 'parity',
            'file' => 'fronts/acme/pages/services.en.md',
            'message' => 'No es version of "services" (add pages/services.es.md).',
        ]]);
});

it('fails when language versions disagree on layout or forms', function () {
    FrontFixture::create([
        'pages/services.en.md' => acmePage(['layout' => 'page', 'forms' => ['quote']]),
        'pages/services.es.md' => acmePage(['layout' => 'home', 'forms' => ['contact']]),
    ]);

    expect(lintAcme()['errors'])->toBe([
        [
            'rule' => 'parity',
            'file' => 'fronts/acme/pages/services.es.md',
            'message' => 'Frontmatter layout "home" differs from services.en.md ("page"); every language version needs the same layout.',
        ],
        [
            'rule' => 'parity',
            'file' => 'fronts/acme/pages/services.es.md',
            'message' => 'Frontmatter forms "["contact"]" differs from services.en.md ("["quote"]"); every language version needs the same forms.',
        ],
    ]);
});

it('rejects rating, review and certification claims in JSON-LD', function (array $jsonld, string $claim) {
    FrontFixture::create(['pages/services.en.md' => acmePage(['jsonld' => $jsonld])]);

    $errors = lintAcme()['errors'];

    expect($errors)->toHaveCount(1)
        ->and($errors[0]['rule'])->toBe('jsonld')
        ->and($errors[0]['message'])->toStartWith('JSON-LD may not claim "'.$claim.'"');
})->with([
    'aggregate rating' => [['@type' => 'Service', 'aggregateRating' => ['ratingValue' => 5]], 'aggregateRating'],
    'review' => [['@type' => 'Service', 'review' => ['reviewBody' => 'Great']], 'review'],
    'certification' => [['@type' => 'Service', 'hasCertification' => ['name' => 'Green Seal']], 'hasCertification'],
    'review object' => [['@type' => 'Service', 'subjectOf' => ['@type' => 'Review']], 'Review'],
]);

it('reports files the front cannot serve', function () {
    FrontFixture::create([
        'pages/about.md' => acmePage(),
        'pages/about.fr.md' => acmePage(),
        'pages/broken.en.md' => "---\ntitle: [unclosed\n---\n\nBody",
    ]);

    $errors = collect(lintAcme()['errors'])->map(fn (array $error): string => $error['rule'].' '.$error['file'])->all();

    expect($errors)->toContain(
        'pages fronts/acme/pages/about.md',
        'languages fronts/acme/pages/about.fr.md',
        'frontmatter fronts/acme/pages/broken.en.md',
    );
});

it('stops at a missing or broken facts.yaml', function (?string $facts, string $message) {
    FrontFixture::create(['facts.yaml' => $facts]);

    $report = lintAcme();

    expect($report['ok'])->toBeFalse()
        ->and($report['errors'])->toHaveCount(1)
        ->and($report['errors'][0]['file'])->toBe('fronts/acme/facts.yaml')
        ->and($report['errors'][0]['message'])->toStartWith($message);
})->with([
    'missing' => [null, 'facts.yaml is missing.'],
    'invalid YAML' => ["name: [unclosed\n", 'facts.yaml is not valid YAML'],
]);

it('flags never-write entries that YAML read as empty', function () {
    $facts = FrontFixture::defaultFacts();
    unset($facts['never_write']);

    FrontFixture::create(['facts.yaml' => Yaml::dump($facts, 4, 2)."never_write:\n  - certified\n  - #1\n"]);

    expect(lintAcme()['errors'])->toContain([
        'rule' => 'facts',
        'file' => 'fronts/acme/facts.yaml',
        'message' => 'facts.never_write[1] is empty or not text. Quote phrases that start with # (for example "#1").',
    ]);
});

it('reports discovery files that fail to render', function () {
    FrontFixture::create();
    $this->mock(FrontDiscovery::class)
        ->shouldReceive('robots')
        ->andThrow(new RuntimeException('Sitemap URL could not be built.'));

    expect(lintAcme()['errors'])->toBe([[
        'rule' => 'discovery',
        'file' => 'fronts/acme',
        'message' => 'Discovery files failed to render: Sitemap URL could not be built.',
    ]]);
});

it('fails when the front folder does not exist', function () {
    FrontFixture::create();

    expect(app(FrontLinter::class)->lint('globex')['errors'])->toBe([[
        'rule' => 'front',
        'file' => 'fronts/globex',
        'message' => 'The front folder does not exist.',
    ]]);
});
