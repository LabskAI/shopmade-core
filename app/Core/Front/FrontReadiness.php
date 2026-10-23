<?php

namespace App\Core\Front;

use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Agent-readiness checks over HTTP against a running front: home and every page answer 200,
 * robots.txt carries Sitemap and Content-Signal, sitemap.xml lists every page with hreflang
 * alternates, llms.txt exists, and markdown negotiation works.
 */
class FrontReadiness
{
    private const BROWSER_ACCEPT = 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.8';

    public function __construct(private FrontKit $kit) {}

    /**
     * $baseUrl is the front root, e.g. https://example.com/site or https://demo.example.com. A bare host
     * root that is not config('agents.front.host') gets /{agents.front.prefix} appended, as routed.
     * A query string in $baseUrl is merged into $query, which is appended to every request. The
     * expected pages come from fronts/{company} under $basePath (default: the repo).
     *
     * @param  array<string, string>  $query
     * @return array{ok: bool, checks: list<array{name: string, ok: bool, detail: string}>}
     */
    public function check(string $baseUrl, string $company, array $query = [], ?string $basePath = null): array
    {
        [$root, $query] = $this->frontRoot($baseUrl, $query);
        $site = new FrontSite($this->kit, $company, $basePath);
        $cookies = new CookieJar;

        $get = function (string $path, string $accept = self::BROWSER_ACCEPT) use ($root, $query, $cookies): ?Response {
            try {
                return Http::connectTimeout(5)
                    ->timeout(15)
                    ->withOptions(['cookies' => $cookies])
                    ->withUserAgent('ShopmadeReadiness/1.0')
                    ->withHeaders(['Accept' => $accept])
                    ->get($path === '' ? $root : $root.'/'.$path, $query);
            } catch (ConnectionException) {
                return null;
            }
        };

        $home = $get('');

        $checks = [
            $this->homeCheck($home),
            $this->pagesCheck($site, $get),
            $this->robotsCheck($get('robots.txt')),
            $this->sitemapCheck($site, $root, $get('sitemap.xml')),
            $this->llmsCheck($get('llms.txt')),
            $this->markdownCheck($get('', 'text/markdown')),
        ];

        return [
            'ok' => collect($checks)->every(fn (array $check): bool => $check['ok']),
            'checks' => $checks,
        ];
    }

    /**
     * @return array{name: string, ok: bool, detail: string}
     */
    private function homeCheck(?Response $response): array
    {
        if ($response?->status() !== 200) {
            return $this->result('home', false, $this->failure($response));
        }

        if (preg_match('/<title[^>]*>\s*([^<]*?)\s*<\/title>/i', $response->body(), $match) !== 1 || $match[1] === '') {
            return $this->result('home', false, 'HTTP 200 but the page has no <title>.');
        }

        return $this->result('home', true, 'HTTP 200, title "'.html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5).'".');
    }

    /**
     * @param  callable(string, string=): ?Response  $get
     * @return array{name: string, ok: bool, detail: string}
     */
    private function pagesCheck(FrontSite $site, callable $get): array
    {
        $paths = [];

        foreach ($site->pages() as $page) {
            $path = $site->relativePath($page['slug'], $page['lang']);

            if ($path !== '') {
                $paths[] = $path;
            }
        }

        $failures = [];

        foreach ($paths as $path) {
            $response = $get($path);

            if ($response?->status() !== 200) {
                $failures[] = '/'.$path.' ('.$this->failure($response).')';
            }
        }

        if ($failures !== []) {
            return $this->result('pages', false, 'Not 200: '.implode(', ', $failures).'.');
        }

        return $this->result('pages', true, $paths === []
            ? 'The front has no pages besides the home page.'
            : count($paths).' page(s) answered HTTP 200.');
    }

    /**
     * @return array{name: string, ok: bool, detail: string}
     */
    private function robotsCheck(?Response $response): array
    {
        if ($response?->status() !== 200) {
            return $this->result('robots', false, 'robots.txt: '.$this->failure($response));
        }

        $missing = array_keys(array_filter([
            'Sitemap:' => preg_match('/^sitemap:\s*\S+/im', $response->body()) !== 1,
            'Content-Signal:' => preg_match('/^content-signal:\s*\S+/im', $response->body()) !== 1,
        ]));

        return $missing === []
            ? $this->result('robots', true, 'robots.txt has Sitemap and Content-Signal.')
            : $this->result('robots', false, 'robots.txt has no '.implode(' or ', $missing).' line.');
    }

    /**
     * @return array{name: string, ok: bool, detail: string}
     */
    private function sitemapCheck(FrontSite $site, string $root, ?Response $response): array
    {
        if ($response?->status() !== 200) {
            return $this->result('sitemap', false, 'sitemap.xml: '.$this->failure($response));
        }

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($response->body(), options: LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($xml === false) {
            return $this->result('sitemap', false, 'sitemap.xml is not valid XML.');
        }

        $xml->registerXPathNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $listed = [];

        foreach ($xml->xpath('//s:url') ?: [] as $url) {
            $url->registerXPathNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');
            $url->registerXPathNamespace('x', 'http://www.w3.org/1999/xhtml');
            $loc = (string) ($url->xpath('s:loc')[0] ?? '');
            $languages = array_map(fn ($attribute): string => (string) $attribute, $url->xpath('x:link[@rel="alternate"]/@hreflang') ?: []);
            $listed[$this->normalize($loc)] = $languages;
        }

        $missing = [];
        $withoutAlternates = [];

        foreach ($site->indexablePages() as $page) {
            $path = $site->relativePath($page['slug'], $page['lang']);
            $key = $this->normalize($path === '' ? $root : $root.'/'.$path);

            if (! array_key_exists($key, $listed)) {
                $missing[] = '/'.$path;

                continue;
            }

            $expected = array_keys(array_filter($site->translations($page['slug']), fn (array $version): bool => $site->isIndexable($version)));

            if (array_diff($expected, $listed[$key]) !== []) {
                $withoutAlternates[] = '/'.$path;
            }
        }

        if ($missing !== [] || $withoutAlternates !== []) {
            return $this->result('sitemap', false, trim(
                ($missing !== [] ? 'Not listed: '.implode(', ', $missing).'. ' : '')
                .($withoutAlternates !== [] ? 'Missing hreflang alternates: '.implode(', ', $withoutAlternates).'.' : ''),
            ));
        }

        return $this->result('sitemap', true, count($listed).' URL(s) listed with hreflang alternates.');
    }

    /**
     * @return array{name: string, ok: bool, detail: string}
     */
    private function llmsCheck(?Response $response): array
    {
        if ($response?->status() !== 200) {
            return $this->result('llms', false, 'llms.txt: '.$this->failure($response));
        }

        return str_starts_with(ltrim($response->body()), '# ')
            ? $this->result('llms', true, 'llms.txt is present.')
            : $this->result('llms', false, 'llms.txt does not start with a "# " title.');
    }

    /**
     * @return array{name: string, ok: bool, detail: string}
     */
    private function markdownCheck(?Response $response): array
    {
        if ($response?->status() !== 200) {
            return $this->result('markdown', false, 'Accept: text/markdown: '.$this->failure($response));
        }

        $problems = array_keys(array_filter([
            'Content-Type is not text/markdown' => ! str_contains(strtolower($response->header('Content-Type')), 'text/markdown'),
            'no Vary: Accept' => ! str_contains(strtolower($response->header('Vary')), 'accept'),
            'the body does not start with frontmatter' => ! str_starts_with($response->body(), '---'),
        ]));

        return $problems === []
            ? $this->result('markdown', true, 'Accept: text/markdown returns the page source with Vary: Accept.')
            : $this->result('markdown', false, Str::ucfirst(implode('; ', $problems)).'.');
    }

    /**
     * @param  array<string, string>  $query
     * @return array{0: string, 1: array<string, string>}
     */
    private function frontRoot(string $baseUrl, array $query): array
    {
        $parts = parse_url(trim($baseUrl));
        $parts = is_array($parts) ? $parts : [];

        parse_str($parts['query'] ?? '', $embedded);

        $host = strtolower($parts['host'] ?? '');
        $path = rtrim($parts['path'] ?? '', '/');

        if ($path === '' && FrontSite::prefix() !== '' && $host !== FrontSite::configuredHost()) {
            $path = '/'.FrontSite::prefix();
        }

        $root = ($parts['scheme'] ?? 'https').'://'.$host.(isset($parts['port']) ? ':'.$parts['port'] : '').$path;

        return [$root, array_map('strval', [...array_filter($embedded, 'is_string'), ...$query])];
    }

    /**
     * host + path, lowercase host, no scheme, no trailing slash: the same page over http or https matches.
     */
    private function normalize(string $url): string
    {
        $parts = parse_url($url);

        return strtolower($parts['host'] ?? '').(isset($parts['port']) ? ':'.$parts['port'] : '').rtrim($parts['path'] ?? '', '/');
    }

    private function failure(?Response $response): string
    {
        return $response === null ? 'no response (connection failed)' : 'HTTP '.$response->status();
    }

    /**
     * @return array{name: string, ok: bool, detail: string}
     */
    private function result(string $name, bool $ok, string $detail): array
    {
        return ['name' => $name, 'ok' => $ok, 'detail' => $detail];
    }
}
