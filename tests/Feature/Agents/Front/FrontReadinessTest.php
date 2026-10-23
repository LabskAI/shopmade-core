<?php

use App\Core\Front\FrontReadiness;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Agents\Front\FrontFixture;
use Tests\TestCase;

afterEach(fn () => FrontFixture::cleanup());

/**
 * Answer the readiness requests with this app's own front, except for the overridden paths.
 *
 * @param  array<string, PromiseInterface>  $overrides
 */
function serveFrontToReadiness(TestCase $test, array $overrides = []): void
{
    Http::preventStrayRequests();
    Http::fake(function (Request $request) use ($test, $overrides): PromiseInterface {
        $path = (string) parse_url($request->url(), PHP_URL_PATH);

        if (array_key_exists($path, $overrides)) {
            return $overrides[$path];
        }

        $query = parse_url($request->url(), PHP_URL_QUERY);
        $response = $test->call('GET', $path.(is_string($query) ? '?'.$query : ''), server: [
            'HTTP_ACCEPT' => $request->header('Accept')[0] ?? '*/*',
        ]);

        return Http::response($response->getContent(), $response->getStatusCode(), $response->headers->all());
    });
}

it('passes every check on a healthy front and sends the query with every request', function () {
    FrontFixture::create();
    serveFrontToReadiness($this);

    $report = app(FrontReadiness::class)->check(url('/site'), 'acme', ['s' => 'signature', 'e' => '1760000000']);

    expect($report['ok'])->toBeTrue()
        ->and(array_column($report['checks'], 'name'))->toBe(['home', 'pages', 'robots', 'sitemap', 'llms', 'markdown']);
    Http::assertSentCount(8);
    Http::assertNotSent(function (Request $request): bool {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return $query !== ['s' => 'signature', 'e' => '1760000000'];
    });
});

it('fails the robots check when Content-Signal is missing', function () {
    FrontFixture::create();
    serveFrontToReadiness($this, [
        '/site/robots.txt' => Http::response("User-agent: *\nAllow: /\nSitemap: ".url('/site/sitemap.xml')."\n"),
    ]);

    $report = app(FrontReadiness::class)->check(url('/site'), 'acme');

    expect($report['ok'])->toBeFalse()
        ->and(collect($report['checks'])->firstWhere('name', 'robots'))
        ->toBe(['name' => 'robots', 'ok' => false, 'detail' => 'robots.txt has no Content-Signal: line.']);
});

it('names the pages that do not answer 200', function () {
    FrontFixture::create();
    serveFrontToReadiness($this, ['/site/es/services' => Http::response('Not found', 404)]);

    $report = app(FrontReadiness::class)->check(url('/site'), 'acme');

    expect(collect($report['checks'])->firstWhere('name', 'pages'))
        ->toBe(['name' => 'pages', 'ok' => false, 'detail' => 'Not 200: /es/services (HTTP 404).']);
});

it('fails the sitemap check for a page the sitemap leaves out', function () {
    FrontFixture::create();
    serveFrontToReadiness($this, [
        '/site/sitemap.xml' => Http::response('<?xml version="1.0"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>'.url('/site').'</loc></url></urlset>'),
    ]);

    $report = app(FrontReadiness::class)->check(url('/site'), 'acme');

    expect(collect($report['checks'])->firstWhere('name', 'sitemap'))->toBe([
        'name' => 'sitemap',
        'ok' => false,
        'detail' => 'Not listed: /es, /services, /es/services. Missing hreflang alternates: /.',
    ]);
});

it('takes the query from a signed base URL and the front prefix for a bare host', function () {
    FrontFixture::create();
    serveFrontToReadiness($this);

    $report = app(FrontReadiness::class)->check(url('/').'/?s=signature&e=1760000000', 'acme');

    expect($report['ok'])->toBeTrue();
    Http::assertSent(fn (Request $request): bool => $request->url() === url('/site/robots.txt').'?s=signature&e=1760000000');
});

it('reports a front that cannot be reached', function () {
    FrontFixture::create();
    Http::preventStrayRequests();
    Http::fake(['unreachable.example/*' => Http::failedConnection()]);

    $report = app(FrontReadiness::class)->check('https://unreachable.example/site', 'acme');

    expect($report['ok'])->toBeFalse()
        ->and($report['checks'][0])->toBe(['name' => 'home', 'ok' => false, 'detail' => 'no response (connection failed)']);
});
