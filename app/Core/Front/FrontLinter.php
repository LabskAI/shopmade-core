<?php

namespace App\Core\Front;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Throwable;

/**
 * The rules behind `php artisan front:lint <company>` (docs/front/README.md). Deterministic:
 * the same files always give the same errors. Every message names the file and quotes the
 * offending text.
 */
class FrontLinter
{
    /**
     * Prices and currency, in figures or words. Prices are the office agent's business.
     */
    private const CURRENCY_PATTERNS = [
        '/(?:US|CA|MX)?\$\s?\d[\d.,]*/u',
        '/(?<![\p{L}\p{N}_])(?:USD|EUR|GBP|CAD|MXN)\s?\d[\d.,]*/iu',
        '/\d[\d.,]*\s?(?:USD|EUR|GBP|CAD|MXN)(?![\p{L}\p{N}_])/iu',
        '/[€£¥]\s?[\d.,]*/u',
        '/(?<![\p{L}\p{N}_])(?:dollars?|d[oó]lares|euros)(?![\p{L}\p{N}_])/iu',
    ];

    public function __construct(
        private FrontKit $kit,
        private FrontDiscovery $discovery,
        private FrontJsonLd $jsonLd,
    ) {}

    /**
     * @return array{ok: bool, errors: list<array{rule: string, file: string, message: string}>, warnings: list<array{rule: string, file: string, message: string}>}
     */
    public function lint(string $company, ?string $basePath = null): array
    {
        $front = trim((string) config('agents.paths.fronts', 'fronts'), '/').'/'.$company;

        if (! $this->kit->exists($company, $basePath)) {
            return $this->report([$this->issue('error', 'front', $front, 'The front folder does not exist.')]);
        }

        [$facts, $issues] = $this->checkFacts($company, $basePath, $front);

        if ($facts === null) {
            return $this->report($issues);
        }

        $languages = $this->kit->languages($company, $basePath);
        $neverWrite = $this->kit->neverWrite($company, $basePath);

        [$pages, $pageIssues] = $this->readPages($company, $basePath, $front);
        array_push($issues, ...$pageIssues);

        foreach ($pages as $page) {
            array_push($issues, ...$this->checkPage($page, $facts, $languages, $neverWrite));
        }

        array_push(
            $issues,
            ...$this->checkParity($pages, $languages),
            ...$this->checkStrings($company, $basePath, $front, $neverWrite),
            ...$this->checkSkills($company, $basePath, $front),
            ...$this->checkDiscovery($company, $basePath, $front),
        );

        return $this->report($issues);
    }

    /**
     * @return array{0: array<string, mixed>|null, 1: list<array{level: string, rule: string, file: string, message: string}>}
     */
    private function checkFacts(string $company, ?string $basePath, string $front): array
    {
        $file = $front.'/facts.yaml';
        $yaml = $this->kit->factsYaml($company, $basePath);

        if ($yaml === null) {
            return [null, [$this->issue('error', 'facts', $file, 'facts.yaml is missing.')]];
        }

        try {
            $facts = Yaml::parse($yaml);
        } catch (ParseException $exception) {
            return [null, [$this->issue('error', 'facts', $file, 'facts.yaml is not valid YAML: '.$exception->getMessage())]];
        }

        if (! is_array($facts) || array_is_list($facts)) {
            return [null, [$this->issue('error', 'facts', $file, 'facts.yaml must be a YAML mapping (key: value lines).')]];
        }

        $issues = [];
        $error = function (string $message) use (&$issues, $file): void {
            $issues[] = $this->issue('error', 'facts', $file, $message);
        };

        if (! is_string($facts['name'] ?? null) || trim($facts['name']) === '') {
            $error('facts.name is required.');
        }

        $languages = $facts['languages'] ?? null;

        if (! is_array($languages) || ! array_is_list($languages) || $languages === []) {
            $error('facts.languages must list at least one two-letter language code, e.g. [en, es].');
        } else {
            foreach ($languages as $index => $language) {
                if (! is_string($language) || preg_match('/^'.FrontKit::LANGUAGE_PATTERN.'$/', $language) !== 1) {
                    $error(sprintf('facts.languages[%d] "%s" is not a two-letter lowercase language code.', $index, $this->show($language)));
                }
            }
        }

        $cities = $facts['cities'] ?? [];

        if (! is_array($cities) || ! array_is_list($cities)) {
            $error('facts.cities must be a list of city names.');
        } elseif ($cities === []) {
            $issues[] = $this->issue('warning', 'facts', $file, 'facts.cities is empty, so no page can name a service area.');
        } else {
            foreach ($cities as $index => $city) {
                if (! is_string($city) || trim($city) === '') {
                    $error(sprintf('facts.cities[%d] must be a city name.', $index));
                }
            }
        }

        $services = $facts['services'] ?? [];

        if (! is_array($services) || ! array_is_list($services)) {
            $error('facts.services must be a list of {slug, name} entries.');
        } else {
            foreach ($services as $index => $service) {
                $slug = is_array($service) ? ($service['slug'] ?? null) : null;
                $name = is_array($service) ? ($service['name'] ?? null) : null;

                if (! is_string($slug) || preg_match('/^'.FrontKit::SLUG_PATTERN.'$/', $slug) !== 1) {
                    $error(sprintf('facts.services[%d] needs a lowercase slug such as "house-cleaning".', $index));
                }

                $names = is_array($name) ? $name : [$name];

                if ($names === [] || array_filter($names, fn (mixed $value): bool => ! is_string($value) || trim($value) === '') !== []) {
                    $error(sprintf('facts.services[%d] needs a name (text, or one text per language).', $index));
                }
            }
        }

        $neverWrite = $facts['never_write'] ?? [];

        if (! is_array($neverWrite) || ! array_is_list($neverWrite)) {
            $error('facts.never_write must be a list of phrases.');
        } else {
            foreach ($neverWrite as $index => $phrase) {
                if (! is_string($phrase) || trim($phrase) === '') {
                    $error(sprintf('facts.never_write[%d] is empty or not text. Quote phrases that start with # (for example "#1").', $index));
                }
            }
        }

        $discovery = $facts['discovery'] ?? [];

        if (! is_array($discovery)) {
            $error('facts.discovery must be a mapping with ai_search and ai_training.');
        } else {
            foreach (['ai_search', 'ai_training'] as $key) {
                if (array_key_exists($key, $discovery) && ! is_bool($discovery[$key])) {
                    $error(sprintf('facts.discovery.%s must be true or false.', $key));
                }
            }
        }

        $address = $facts['address'] ?? [];

        if (is_array($address) && array_key_exists('publish', $address) && ! is_bool($address['publish'])) {
            $error('facts.address.publish must be true or false.');
        }

        return [$facts, $issues];
    }

    /**
     * @return array{0: list<array{slug: string, lang: string, file: string, frontmatter: array<string, mixed>, body: string}>, 1: list<array{level: string, rule: string, file: string, message: string}>}
     */
    private function readPages(string $company, ?string $basePath, string $front): array
    {
        $pages = [];
        $issues = [];

        foreach ($this->kit->files($company, 'pages', $basePath) as $file) {
            $path = $front.'/pages/'.$file;

            if (! str_ends_with($file, '.md')) {
                $issues[] = $this->issue('warning', 'pages', $path, 'Only markdown pages (<slug>.<lang>.md) are served; this file is ignored.');

                continue;
            }

            if (preg_match('/^('.FrontKit::SLUG_PATTERN.')\.('.FrontKit::LANGUAGE_PATTERN.')\.md$/', $file, $match) !== 1) {
                $issues[] = $this->issue('error', 'pages', $path, 'Page files are named <slug>.<lang>.md with a lowercase slug, e.g. pages/about.en.md.');

                continue;
            }

            $parsed = FrontMatter::parse((string) $this->kit->read($company, 'pages/'.$file, $basePath));

            if ($parsed['error'] !== null) {
                $issues[] = $this->issue('error', 'frontmatter', $path, $parsed['error']);
            }

            $pages[] = [
                'slug' => $match[1],
                'lang' => $match[2],
                'file' => $path,
                'frontmatter' => $parsed['frontmatter'],
                'body' => $parsed['body'],
            ];
        }

        return [$pages, $issues];
    }

    /**
     * @param  array{slug: string, lang: string, file: string, frontmatter: array<string, mixed>, body: string}  $page
     * @param  array<string, mixed>  $facts
     * @param  list<string>  $languages
     * @param  list<string>  $neverWrite
     * @return list<array{level: string, rule: string, file: string, message: string}>
     */
    private function checkPage(array $page, array $facts, array $languages, array $neverWrite): array
    {
        $issues = [];
        $file = $page['file'];
        $frontmatter = $page['frontmatter'];

        if (! in_array($page['lang'], $languages, true)) {
            $issues[] = $this->issue('error', 'languages', $file, sprintf(
                'Language "%s" is not in facts.languages (%s), so this page is never served.',
                $page['lang'],
                implode(', ', $languages),
            ));
        }

        foreach (['title', 'description'] as $key) {
            if (! is_string($frontmatter[$key] ?? null) || trim($frontmatter[$key]) === '') {
                $issues[] = $this->issue('error', 'frontmatter', $file, sprintf('Frontmatter "%s" is required.', $key));
            }
        }

        if (array_key_exists('layout', $frontmatter) && ! in_array($frontmatter['layout'], FrontSite::LAYOUTS, true)) {
            $issues[] = $this->issue('warning', 'layout', $file, sprintf(
                'Unknown layout "%s"; the page renders with "page". Layouts: %s.',
                $this->show($frontmatter['layout']),
                implode(', ', FrontSite::LAYOUTS),
            ));
        }

        foreach ($this->texts($page) as [$where, $text]) {
            foreach ($this->findPhrases($text, $neverWrite) as [$phrase, $excerpt]) {
                $issues[] = $this->issue('error', 'never_write', $file, sprintf('Never-write phrase "%s" in %s: "%s"', $phrase, $where, $excerpt));
            }

            foreach ($this->findCurrency($text) as [$amount, $excerpt]) {
                $issues[] = $this->issue('error', 'currency', $file, sprintf(
                    'Currency amount "%s" in %s: "%s" (prices are quoted by the office, not on the front).',
                    $amount,
                    $where,
                    $excerpt,
                ));
            }
        }

        $cities = array_values(array_filter((array) ($facts['cities'] ?? []), 'is_string'));
        $knownCities = array_map(fn (string $city): string => mb_strtolower(trim($city)), $cities);

        foreach (['city', 'cities', 'area', 'areas'] as $key) {
            foreach ((array) ($frontmatter[$key] ?? []) as $city) {
                if (! is_string($city) || ! in_array(mb_strtolower(trim($city)), $knownCities, true)) {
                    $issues[] = $this->issue('error', 'cities', $file, sprintf(
                        'Frontmatter %s "%s" is not in facts.cities (%s).',
                        $key,
                        $this->show($city),
                        implode(', ', $cities),
                    ));
                }
            }
        }

        $serviceSlugs = array_values(array_filter(array_map(
            fn (mixed $service): mixed => is_array($service) ? ($service['slug'] ?? null) : null,
            (array) ($facts['services'] ?? []),
        ), 'is_string'));

        foreach (['service', 'services'] as $key) {
            foreach ((array) ($frontmatter[$key] ?? []) as $service) {
                if (! is_string($service) || ! in_array($service, $serviceSlugs, true)) {
                    $issues[] = $this->issue('error', 'services', $file, sprintf(
                        'Frontmatter %s "%s" is not a slug in facts.services (%s).',
                        $key,
                        $this->show($service),
                        implode(', ', $serviceSlugs),
                    ));
                }
            }
        }

        foreach ($this->jsonLd->forbiddenClaims($frontmatter['jsonld'] ?? null) as $claim) {
            $issues[] = $this->issue('error', 'jsonld', $file, sprintf(
                'JSON-LD may not claim "%s" (%s): no ratings, reviews, awards or certifications.',
                $claim['claim'],
                $claim['path'],
            ));
        }

        return $issues;
    }

    /**
     * A page missing in a language is a warning; language versions that disagree on layout or forms are errors.
     *
     * @param  list<array{slug: string, lang: string, file: string, frontmatter: array<string, mixed>, body: string}>  $pages
     * @param  list<string>  $languages
     * @return list<array{level: string, rule: string, file: string, message: string}>
     */
    private function checkParity(array $pages, array $languages): array
    {
        $issues = [];
        $bySlug = [];

        foreach ($pages as $page) {
            if (in_array($page['lang'], $languages, true)) {
                $bySlug[$page['slug']][$page['lang']] = $page;
            }
        }

        foreach ($bySlug as $slug => $versions) {
            $reference = $versions[$languages[0]] ?? reset($versions);

            foreach ($languages as $lang) {
                if (! isset($versions[$lang])) {
                    $issues[] = $this->issue('warning', 'parity', $reference['file'], sprintf(
                        'No %s version of "%s" (add pages/%s.%s.md).',
                        $lang,
                        $slug,
                        $slug,
                        $lang,
                    ));
                }
            }

            foreach ($versions as $page) {
                if ($page['file'] === $reference['file']) {
                    continue;
                }

                foreach (['layout', 'forms'] as $key) {
                    $expected = $this->parityValue($reference['frontmatter'], $key);
                    $actual = $this->parityValue($page['frontmatter'], $key);

                    if ($expected !== $actual) {
                        $issues[] = $this->issue('error', 'parity', $page['file'], sprintf(
                            'Frontmatter %s "%s" differs from %s ("%s"); every language version needs the same %s.',
                            $key,
                            $this->show($actual),
                            basename($reference['file']),
                            $this->show($expected),
                            $key,
                        ));
                    }
                }
            }
        }

        return $issues;
    }

    /**
     * @param  list<string>  $neverWrite
     * @return list<array{level: string, rule: string, file: string, message: string}>
     */
    private function checkStrings(string $company, ?string $basePath, string $front, array $neverWrite): array
    {
        $issues = [];

        foreach ($this->kit->files($company, 'lang', $basePath) as $file) {
            if (! str_ends_with($file, '.yaml') && ! str_ends_with($file, '.yml')) {
                continue;
            }

            $path = $front.'/lang/'.$file;

            try {
                $strings = Yaml::parse((string) $this->kit->read($company, 'lang/'.$file, $basePath));
            } catch (ParseException $exception) {
                $issues[] = $this->issue('error', 'strings', $path, 'Not valid YAML: '.$exception->getMessage());

                continue;
            }

            if (! is_array($strings)) {
                if ($strings !== null) {
                    $issues[] = $this->issue('error', 'strings', $path, 'UI strings must be a YAML mapping (key: text).');
                }

                continue;
            }

            foreach ($this->flatten($strings) as $key => $text) {
                foreach ($this->findPhrases($text, $neverWrite) as [$phrase, $excerpt]) {
                    $issues[] = $this->issue('error', 'never_write', $path, sprintf('Never-write phrase "%s" in string %s: "%s"', $phrase, $key, $excerpt));
                }
            }
        }

        return $issues;
    }

    /**
     * @return list<array{level: string, rule: string, file: string, message: string}>
     */
    private function checkSkills(string $company, ?string $basePath, string $front): array
    {
        $issues = [];

        foreach ($this->kit->files($company, 'skills', $basePath) as $file) {
            if (! str_ends_with($file, '.md')) {
                continue;
            }

            $path = $front.'/skills/'.$file;
            $parsed = FrontMatter::parse((string) $this->kit->read($company, 'skills/'.$file, $basePath));
            $name = $parsed['frontmatter']['name'] ?? null;
            $description = $parsed['frontmatter']['description'] ?? null;

            if ($parsed['error'] !== null) {
                $issues[] = $this->issue('warning', 'skills', $path, $parsed['error']);

                continue;
            }

            if (! is_string($name) || trim($name) === '') {
                $issues[] = $this->issue('warning', 'skills', $path, 'Skill frontmatter needs a name.');
            } elseif ($name !== substr($file, 0, -3)) {
                $issues[] = $this->issue('warning', 'skills', $path, sprintf('Skill name "%s" differs from the file name; skills are looked up by file name.', $name));
            }

            if (! is_string($description) || trim($description) === '') {
                $issues[] = $this->issue('warning', 'skills', $path, 'Skill frontmatter needs a description.');
            }
        }

        return $issues;
    }

    /**
     * The generated files and every page must render without an exception.
     *
     * @return list<array{level: string, rule: string, file: string, message: string}>
     */
    private function checkDiscovery(string $company, ?string $basePath, string $front): array
    {
        $site = new FrontSite($this->kit, $company, $basePath);

        try {
            $this->discovery->robots($site);
            $sitemap = $this->discovery->sitemap($site);
            $this->discovery->llms($site);
            $this->discovery->llmsFull($site);

            foreach ($site->pages() as $page) {
                $this->jsonLd->scripts($site, $page);
                $site->bodyHtml($page);
            }
        } catch (Throwable $exception) {
            return [$this->issue('error', 'discovery', $front, 'Discovery files failed to render: '.$exception->getMessage())];
        }

        $previous = libxml_use_internal_errors(true);
        $wellFormed = simplexml_load_string($sitemap, options: LIBXML_NONET) !== false;
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $wellFormed) {
            return [$this->issue('error', 'discovery', $front, 'sitemap.xml is not well-formed XML.')];
        }

        return [];
    }

    /**
     * The texts of a page that are scanned: the body and every frontmatter string.
     *
     * @param  array{frontmatter: array<string, mixed>, body: string}  $page
     * @return list<array{0: string, 1: string}>
     */
    private function texts(array $page): array
    {
        $texts = [['body', $page['body']]];

        foreach ($this->flatten($page['frontmatter']) as $key => $text) {
            $texts[] = ['frontmatter '.$key, $text];
        }

        return $texts;
    }

    /**
     * Every string in a nested array, keyed by its dotted path.
     *
     * @param  array<array-key, mixed>  $values
     * @return array<string, string>
     */
    private function flatten(array $values, string $prefix = ''): array
    {
        $flat = [];

        foreach ($values as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (is_array($value)) {
                $flat += $this->flatten($value, $path);
            } elseif (is_string($value)) {
                $flat[$path] = $value;
            }
        }

        return $flat;
    }

    /**
     * Case-insensitive, whole-word matches of each phrase; whitespace inside a phrase matches any whitespace.
     *
     * @param  list<string>  $phrases
     * @return list<array{0: string, 1: string}>
     */
    private function findPhrases(string $text, array $phrases): array
    {
        $found = [];

        foreach ($phrases as $phrase) {
            $words = preg_split('/\s+/u', trim($phrase), -1, PREG_SPLIT_NO_EMPTY) ?: [];

            if ($words === []) {
                continue;
            }

            $pattern = '/(?<![\p{L}\p{N}_])'.implode('\s+', array_map(fn (string $word): string => preg_quote($word, '/'), $words)).'(?![\p{L}\p{N}_])/iu';

            if (preg_match($pattern, $text, $match, PREG_OFFSET_CAPTURE) === 1) {
                $found[] = [$phrase, $this->excerpt($text, $match[0][1], strlen($match[0][0]))];
            }
        }

        return $found;
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function findCurrency(string $text): array
    {
        $found = [];

        foreach (self::CURRENCY_PATTERNS as $pattern) {
            if (preg_match($pattern, $text, $match, PREG_OFFSET_CAPTURE) === 1) {
                $found[] = [rtrim(trim($match[0][0]), '.,'), $this->excerpt($text, $match[0][1], strlen($match[0][0]))];
            }
        }

        return $found;
    }

    /**
     * The line around a match, shortened to about 60 characters on each side.
     */
    private function excerpt(string $text, int $offset, int $length): string
    {
        $lineStart = strrpos(substr($text, 0, $offset), "\n");
        $lineStart = $lineStart === false ? 0 : $lineStart + 1;
        $lineEnd = strpos($text, "\n", $offset + $length);
        $lineEnd = $lineEnd === false ? strlen($text) : $lineEnd;

        $before = ltrim(substr($text, $lineStart, $offset - $lineStart));
        $after = rtrim(substr($text, $offset + $length, $lineEnd - $offset - $length));

        if (mb_strlen($before) > 60) {
            $before = '…'.mb_substr($before, -60);
        }

        if (mb_strlen($after) > 60) {
            $after = mb_substr($after, 0, 60).'…';
        }

        return $before.substr($text, $offset, $length).$after;
    }

    /**
     * @param  array<string, mixed>  $frontmatter
     */
    private function parityValue(array $frontmatter, string $key): mixed
    {
        if ($key === 'layout') {
            return $frontmatter['layout'] ?? 'page';
        }

        $forms = array_map(
            fn (mixed $form): string => is_array($form) ? $this->show($form['name'] ?? $form['id'] ?? $form['type'] ?? 'form') : $this->show($form),
            (array) ($frontmatter['forms'] ?? []),
        );

        sort($forms);

        return $forms;
    }

    private function show(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_scalar($value) || $value === null) {
            return var_export($value, true);
        }

        return (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array{level: string, rule: string, file: string, message: string}
     */
    private function issue(string $level, string $rule, string $file, string $message): array
    {
        return ['level' => $level, 'rule' => $rule, 'file' => $file, 'message' => $message];
    }

    /**
     * @param  list<array{level: string, rule: string, file: string, message: string}>  $issues
     * @return array{ok: bool, errors: list<array{rule: string, file: string, message: string}>, warnings: list<array{rule: string, file: string, message: string}>}
     */
    private function report(array $issues): array
    {
        $errors = [];
        $warnings = [];
        $seen = [];

        foreach ($issues as $issue) {
            $key = implode("\0", $issue);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $entry = ['rule' => $issue['rule'], 'file' => $issue['file'], 'message' => $issue['message']];

            if ($issue['level'] === 'error') {
                $errors[] = $entry;
            } else {
                $warnings[] = $entry;
            }
        }

        return ['ok' => $errors === [], 'errors' => $errors, 'warnings' => $warnings];
    }
}
