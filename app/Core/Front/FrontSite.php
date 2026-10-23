<?php

namespace App\Core\Front;

use Illuminate\Support\Str;

/**
 * The public face of one company front: its URLs, navigation, UI strings and the public
 * subset of its facts. Resolved per request (company from config('agents.company'), base
 * path from config('agents.paths.repo')) or built explicitly by the linter and readiness check.
 */
class FrontSite
{
    public const LAYOUTS = ['home', 'page'];

    private const DEFAULT_STRINGS = [
        'language_name' => '',
        'home' => 'Home',
        'skip_to_content' => 'Skip to content',
        'menu' => 'Main menu',
        'languages' => 'Languages',
        'call' => 'Call',
        'email' => 'Email',
        'service_area' => 'Service area',
        'services' => 'Services',
        'hours' => 'Hours',
        'follow' => 'Follow',
        'address' => 'Address',
    ];

    private const DEFAULT_DAYS = ['Mo' => 'Mon', 'Tu' => 'Tue', 'We' => 'Wed', 'Th' => 'Thu', 'Fr' => 'Fri', 'Sa' => 'Sat', 'Su' => 'Sun'];

    /**
     * @var array<string, mixed>|null
     */
    private ?array $facts = null;

    /**
     * @var list<string>|null
     */
    private ?array $languages = null;

    /**
     * @var list<array{slug: string, lang: string, path: string, frontmatter: array<string, mixed>, body: string}>|null
     */
    private ?array $pages = null;

    /**
     * Parsed lang/<lang>.yaml files, keyed by language.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $uiStrings = [];

    public function __construct(
        private FrontKit $kit,
        private ?string $company = null,
        private ?string $basePath = null,
    ) {}

    /**
     * The route prefix the front lives under on every host (config agents.front.prefix).
     */
    public static function prefix(): string
    {
        return trim((string) config('agents.front.prefix'), '/');
    }

    /**
     * The host whose root serves the front (config agents.front.host), without scheme or port.
     */
    public static function configuredHost(): ?string
    {
        $host = trim((string) config('agents.front.host'));

        if ($host === '') {
            return null;
        }

        $host = parse_url(str_contains($host, '://') ? $host : 'http://'.$host, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? strtolower($host) : null;
    }

    public function company(): string
    {
        return $this->company ?? (string) config('agents.company');
    }

    public function basePath(): ?string
    {
        return $this->basePath;
    }

    public function exists(): bool
    {
        return $this->kit->exists($this->company(), $this->basePath);
    }

    /**
     * @return array<string, mixed>
     */
    public function facts(): array
    {
        return $this->facts ??= $this->kit->facts($this->company(), $this->basePath);
    }

    public function name(): string
    {
        return $this->text($this->facts()['name'] ?? null) ?? Str::headline($this->company());
    }

    public function legalName(): ?string
    {
        return $this->text($this->facts()['legal_name'] ?? null);
    }

    public function phone(): ?string
    {
        return $this->text($this->facts()['phone'] ?? null);
    }

    public function email(): ?string
    {
        $email = $this->text($this->facts()['email'] ?? null);

        return $email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : null;
    }

    /**
     * @return list<string>
     */
    public function languages(): array
    {
        return $this->languages ??= $this->kit->languages($this->company(), $this->basePath);
    }

    public function defaultLanguage(): string
    {
        return $this->languages()[0];
    }

    public function hasLanguage(string $lang): bool
    {
        return in_array($lang, $this->languages(), true);
    }

    /**
     * @return list<string>
     */
    public function cities(): array
    {
        return $this->stringList($this->facts()['cities'] ?? []);
    }

    /**
     * Services from facts with their name in the given language.
     *
     * @return list<array{slug: string, name: string}>
     */
    public function services(string $lang): array
    {
        $services = $this->facts()['services'] ?? [];
        $resolved = [];

        foreach (is_array($services) ? $services : [] as $service) {
            $slug = is_array($service) ? ($service['slug'] ?? null) : $service;

            if (! is_string($slug) || trim($slug) === '') {
                continue;
            }

            $name = is_array($service) ? ($service['name'] ?? null) : null;

            if (is_array($name)) {
                $name = $name[$lang] ?? $name[$this->defaultLanguage()] ?? reset($name);
            }

            $resolved[] = ['slug' => trim($slug), 'name' => $this->text($name) ?? Str::headline($slug)];
        }

        return $resolved;
    }

    public function serviceName(string $slug, string $lang): ?string
    {
        foreach ($this->services($lang) as $service) {
            if ($service['slug'] === $slug) {
                return $service['name'];
            }
        }

        return null;
    }

    /**
     * Opening hours as schema.org openingHours strings, e.g. "Mo-Fr 08:00-18:00".
     *
     * @return list<string>
     */
    public function hours(): array
    {
        return $this->stringList($this->facts()['hours'] ?? []);
    }

    /**
     * @return list<string>
     */
    public function localizedHours(string $lang): array
    {
        $days = $this->days($lang);

        return array_map(fn (string $line): string => strtr($line, $days), $this->hours());
    }

    /**
     * Public profile links from facts.social (http and https only).
     *
     * @return list<array{label: string, url: string}>
     */
    public function social(): array
    {
        $social = $this->facts()['social'] ?? [];
        $links = [];

        foreach (is_array($social) ? $social : [] as $key => $entry) {
            $url = is_array($entry) ? ($entry['url'] ?? null) : $entry;

            if (! is_string($url) || ! Str::startsWith($url, ['https://', 'http://'])) {
                continue;
            }

            $label = is_array($entry) ? ($entry['name'] ?? null) : (is_string($key) ? $key : null);
            $links[] = ['label' => $this->text($label) ?? (string) parse_url($url, PHP_URL_HOST), 'url' => $url];
        }

        return $links;
    }

    /**
     * The public parts of facts.address: the street and postal code only when address.publish is true.
     *
     * @return array<string, string>|null
     */
    public function address(): ?array
    {
        $address = $this->facts()['address'] ?? null;

        if (! is_array($address)) {
            return null;
        }

        $fields = $this->publishesAddress()
            ? ['street' => 'streetAddress', 'locality' => 'addressLocality', 'region' => 'addressRegion', 'postal_code' => 'postalCode', 'country' => 'addressCountry']
            : ['locality' => 'addressLocality', 'region' => 'addressRegion', 'country' => 'addressCountry'];

        $public = [];

        foreach ($fields as $key => $property) {
            $value = $this->text(is_scalar($address[$key] ?? null) ? (string) $address[$key] : null);

            if ($value !== null) {
                $public[$property] = $value;
            }
        }

        return $public !== [] ? $public : null;
    }

    public function publishesAddress(): bool
    {
        $address = $this->facts()['address'] ?? null;

        return is_array($address) && ($address['publish'] ?? false) === true;
    }

    public function allowsAiSearch(): bool
    {
        return ($this->facts()['discovery']['ai_search'] ?? true) !== false;
    }

    public function allowsAiTraining(): bool
    {
        return ($this->facts()['discovery']['ai_training'] ?? false) === true;
    }

    /**
     * @return list<array{slug: string, lang: string, path: string, frontmatter: array<string, mixed>, body: string}>
     */
    public function pages(): array
    {
        return $this->pages ??= array_values(array_filter(
            $this->kit->pages($this->company(), $this->basePath),
            fn (array $page): bool => $this->hasLanguage($page['lang']),
        ));
    }

    /**
     * @return array{slug: string, lang: string, path: string, frontmatter: array<string, mixed>, body: string}|null
     */
    public function page(string $slug, string $lang): ?array
    {
        foreach ($this->pages() as $page) {
            if ($page['slug'] === $slug && $page['lang'] === $lang) {
                return $page;
            }
        }

        return null;
    }

    /**
     * The versions of one page, keyed by language, in site language order.
     *
     * @return array<string, array{slug: string, lang: string, path: string, frontmatter: array<string, mixed>, body: string}>
     */
    public function translations(string $slug): array
    {
        $translations = [];

        foreach ($this->pages() as $page) {
            if ($page['slug'] === $slug) {
                $translations[$page['lang']] = $page;
            }
        }

        return $translations;
    }

    /**
     * @param  array{frontmatter: array<string, mixed>}  $page
     */
    public function isIndexable(array $page): bool
    {
        return ($page['frontmatter']['noindex'] ?? false) !== true;
    }

    /**
     * @return list<array{slug: string, lang: string, path: string, frontmatter: array<string, mixed>, body: string}>
     */
    public function indexablePages(): array
    {
        return array_values(array_filter($this->pages(), fn (array $page): bool => $this->isIndexable($page)));
    }

    /**
     * Path of a page below the front root: '' (home), 'services', 'es', 'es/services'.
     */
    public function relativePath(string $slug, string $lang): string
    {
        $segments = [];

        if ($lang !== $this->defaultLanguage()) {
            $segments[] = $lang;
        }

        if ($slug !== 'home') {
            $segments[] = $slug;
        }

        return implode('/', $segments);
    }

    /**
     * The front root: the configured front host, or /{prefix} on the current host.
     */
    public function rootUrl(): string
    {
        $host = self::configuredHost();

        if ($host !== null) {
            return (parse_url(url('/'), PHP_URL_SCHEME) ?: 'https').'://'.$host;
        }

        return rtrim(url(self::prefix()), '/');
    }

    /**
     * The root path for robots.txt rules: '/' on the front host, '/{prefix}/' elsewhere.
     */
    public function rootPath(): string
    {
        $path = trim((string) parse_url($this->rootUrl(), PHP_URL_PATH), '/');

        return $path === '' ? '/' : '/'.$path.'/';
    }

    public function url(string $slug, string $lang): string
    {
        $path = $this->relativePath($slug, $lang);

        return $path === '' ? $this->rootUrl() : $this->rootUrl().'/'.$path;
    }

    public function fileUrl(string $file): string
    {
        return $this->rootUrl().'/'.$file;
    }

    /**
     * @return list<array{label: string, url: string, current: bool}>
     */
    public function navigation(string $lang, string $currentSlug): array
    {
        $strings = $this->strings($lang);
        $items = [];

        foreach ($this->pages() as $index => $page) {
            if ($page['lang'] !== $lang || ($page['frontmatter']['nav'] ?? true) === false) {
                continue;
            }

            $order = $page['frontmatter']['order'] ?? ($page['slug'] === 'home' ? 0 : 100);

            $items[] = [
                'sort' => [is_numeric($order) ? (float) $order : 100.0, $index],
                'label' => $page['slug'] === 'home'
                    ? $strings['home']
                    : ($this->text($page['frontmatter']['nav_label'] ?? null) ?? $this->text($page['frontmatter']['title'] ?? null) ?? Str::headline($page['slug'])),
                'url' => $this->url($page['slug'], $lang),
                'current' => $page['slug'] === $currentSlug,
            ];
        }

        usort($items, fn (array $a, array $b): int => $a['sort'] <=> $b['sort']);

        return array_map(fn (array $item): array => [
            'label' => $item['label'],
            'url' => $item['url'],
            'current' => $item['current'],
        ], $items);
    }

    /**
     * UI strings for a language: lang/<lang>.yaml over the kit defaults.
     *
     * @return array<string, string>
     */
    public function strings(string $lang): array
    {
        $custom = $this->uiStrings($lang);
        $strings = [];

        foreach (self::DEFAULT_STRINGS as $key => $default) {
            $strings[$key] = is_string($custom[$key] ?? null) && trim($custom[$key]) !== '' ? trim($custom[$key]) : $default;
        }

        if ($strings['language_name'] === '') {
            $strings['language_name'] = strtoupper($lang);
        }

        return $strings;
    }

    /**
     * @return array<string, string>
     */
    public function days(string $lang): array
    {
        $custom = $this->uiStrings($lang)['days'] ?? [];
        $days = self::DEFAULT_DAYS;

        foreach (array_keys($days) as $code) {
            if (is_array($custom) && is_string($custom[$code] ?? null) && trim($custom[$code]) !== '') {
                $days[$code] = trim($custom[$code]);
            }
        }

        return $days;
    }

    /**
     * The theme tokens, made safe to inline inside a <style> element.
     */
    public function tokensCss(): string
    {
        return str_replace('</', '<\/', (string) $this->kit->theme($this->company(), $this->basePath));
    }

    /**
     * One line describing the business: the home page description, else the name.
     */
    public function summary(): string
    {
        $home = $this->page('home', $this->defaultLanguage());

        return $this->text($home['frontmatter']['description'] ?? null) ?? $this->name();
    }

    /**
     * The page body as HTML. Raw HTML is stripped and unsafe links dropped; a link whose
     * target is a page slug ("[Services](services)") points at that page in the same language.
     *
     * @param  array{slug: string, lang: string, body: string}  $page
     */
    public function bodyHtml(array $page): string
    {
        $html = Str::markdown($page['body'], [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        return preg_replace_callback(
            '/href="('.FrontKit::SLUG_PATTERN.')"/',
            fn (array $match): string => $this->page($match[1], $page['lang']) !== null
                ? 'href="'.e($this->url($match[1], $page['lang'])).'"'
                : $match[0],
            $html,
        ) ?? $html;
    }

    /**
     * The page as served to `Accept: text/markdown`: frontmatter and body.
     *
     * @param  array{frontmatter: array<string, mixed>, body: string}  $page
     */
    public function markdown(array $page): string
    {
        return FrontMatter::compose($page['frontmatter'], $page['body']);
    }

    /**
     * Everything the page view needs.
     *
     * @param  array{slug: string, lang: string, path: string, frontmatter: array<string, mixed>, body: string}  $page
     * @return array<string, mixed>
     */
    public function viewData(array $page): array
    {
        $lang = $page['lang'];
        $frontmatter = $page['frontmatter'];
        $layout = $frontmatter['layout'] ?? 'page';
        $canonical = $this->text($frontmatter['canonical'] ?? null);
        $phone = $this->phone();

        return [
            'lang' => $lang,
            'title' => $this->text($frontmatter['title'] ?? null) ?? $this->name(),
            'description' => $this->text($frontmatter['description'] ?? null) ?? '',
            'noindex' => ! $this->isIndexable($page),
            'layout' => in_array($layout, self::LAYOUTS, true) ? $layout : 'page',
            'canonical' => $canonical !== null && Str::startsWith($canonical, ['https://', 'http://']) ? $canonical : $this->url($page['slug'], $lang),
            'alternates' => $this->alternates($page['slug']),
            'tokens' => $this->tokensCss(),
            'html' => $this->bodyHtml($page),
            't' => $this->strings($lang),
            'name' => $this->name(),
            'legalName' => $this->legalName() ?? $this->name(),
            'homeUrl' => $this->url('home', $lang),
            'navigation' => $this->navigation($lang, $page['slug']),
            'languageLinks' => $this->languageLinks($page['slug'], $lang),
            'phone' => $phone,
            'phoneHref' => $phone !== null ? preg_replace('/[^\d+]/', '', $phone) : null,
            'email' => $this->email(),
            'address' => $this->publishesAddress() && $this->address() !== null ? implode(', ', $this->address()) : null,
            'cities' => $this->cities(),
            'services' => array_column($this->services($lang), 'name'),
            'hours' => $this->localizedHours($lang),
            'social' => $this->social(),
            'year' => now()->year,
        ];
    }

    /**
     * hreflang alternates of a page, plus x-default for the default-language version.
     *
     * @return list<array{lang: string, url: string}>
     */
    public function alternates(string $slug): array
    {
        $alternates = [];

        foreach (array_keys($this->translations($slug)) as $lang) {
            $alternates[] = ['lang' => $lang, 'url' => $this->url($slug, $lang)];
        }

        if ($this->page($slug, $this->defaultLanguage()) !== null) {
            $alternates[] = ['lang' => 'x-default', 'url' => $this->url($slug, $this->defaultLanguage())];
        }

        return $alternates;
    }

    /**
     * The language switcher: the same page in each language, or that language's home.
     *
     * @return list<array{lang: string, label: string, url: string, current: bool}>
     */
    public function languageLinks(string $slug, string $currentLang): array
    {
        return array_map(fn (string $lang): array => [
            'lang' => $lang,
            'label' => $this->strings($lang)['language_name'],
            'url' => $this->url($this->page($slug, $lang) !== null ? $slug : 'home', $lang),
            'current' => $lang === $currentLang,
        ], $this->languages());
    }

    /**
     * Value of the Link header sent with every page.
     */
    public function linkHeader(): string
    {
        return sprintf(
            '<%s>; rel="sitemap"; type="application/xml", <%s>; rel="describedby"; type="text/plain"',
            $this->fileUrl('sitemap.xml'),
            $this->fileUrl('llms.txt'),
        );
    }

    /**
     * A trimmed single-line string, or null for anything else.
     */
    public function text(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return null;
        }

        $value = trim((string) preg_replace('/\s+/u', ' ', (string) $value));

        return $value !== '' ? $value : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function uiStrings(string $lang): array
    {
        return $this->uiStrings[$lang] ??= $this->kit->uiStrings($this->company(), $lang, $this->basePath);
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $values): array
    {
        $values = is_array($values) ? $values : [$values];

        return array_values(array_filter(array_map(fn (mixed $value): ?string => $this->text($value), $values)));
    }
}
