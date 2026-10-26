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

    /**
     * The opt-in AIFormFlow embed script, loaded only on a page that shows a live form.
     */
    public const EMBED_SCRIPT = 'https://aiformflow.com/embed/v1/form.js';

    /**
     * The only value hero.position may take ("x% y%"), so nothing else reaches the style attribute.
     */
    public const HERO_POSITION = '/^\d{1,3}% \d{1,3}%$/';

    /**
     * Front-root file name => file under assets/ (served by the well-known icon routes).
     */
    public const WELL_KNOWN_ICONS = [
        'favicon.ico' => 'icons/favicon.ico',
        'favicon.svg' => 'icons/favicon.svg',
        'apple-touch-icon.png' => 'icons/apple-touch-icon.png',
        'icon-192.png' => 'icons/icon-192.png',
        'icon-512.png' => 'icons/icon-512.png',
        'site.webmanifest' => 'icons/site.webmanifest',
    ];

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
        'contact' => 'Contact',
        'legal' => 'Legal',
        'open_form' => 'Open the form',
        'open_form_page' => 'Open the form on its own page',
        'form_title' => '',
        'form_lead' => '',
        'form_error' => "The form didn't load.",
        'form_error_help' => 'Open it on its own page, or contact us. Nothing is lost.',
        'prefer_to_talk' => 'Prefer to talk?',
    ];

    /**
     * Switcher labels for common languages when lang/<lang>.yaml has no language_name.
     */
    private const LANGUAGE_NAMES = [
        'en' => 'English',
        'es' => 'Español',
        'pt' => 'Português',
        'fr' => 'Français',
        'de' => 'Deutsch',
        'it' => 'Italiano',
        'nl' => 'Nederlands',
    ];

    /**
     * Hosts an opt-in AIFormFlow embed or script may load from (the host itself and its subdomains).
     */
    public const FORM_HOSTS = ['aiformflow.com', 'aiff.dev'];

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

    private FrontAssets $assets;

    public function __construct(
        private FrontKit $kit,
        private ?string $company = null,
        private ?string $basePath = null,
    ) {
        $this->assets = new FrontAssets($kit);
    }

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
            $strings['language_name'] = self::LANGUAGE_NAMES[$lang] ?? strtoupper($lang);
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
     * The page body as HTML. Allowlisted `:::name` fences become kit components (FrontComponents); the rest is
     * markdown with raw HTML stripped and unsafe links dropped. A link whose target is a page slug
     * ("[Services](services)") points at that page in the same language; `assets/…` links and images point at the file.
     *
     * @param  array{slug: string, lang: string, body: string}  $page
     */
    public function bodyHtml(array $page): string
    {
        return $this->components()->render($page['body'], $page['lang']);
    }

    /**
     * Markdown to sanitized HTML, with page-slug and asset links rewritten.
     */
    public function markdownHtml(string $markdown, string $lang): string
    {
        if (trim($markdown) === '') {
            return '';
        }

        $html = Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        $html = preg_replace_callback(
            '/href="('.FrontKit::SLUG_PATTERN.')((?:\?[^"#]*)?(?:#[^"]*)?)"/',
            fn (array $match): string => $this->page($match[1], $lang) !== null
                ? 'href="'.e($this->url($match[1], $lang).$this->safeSuffix(html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'))).'"'
                : $match[0],
            $html,
        ) ?? $html;

        return preg_replace_callback(
            '/\b(src|href)="((?:assets\/|asset:)[^"]+)"/',
            function (array $match): string {
                $url = $this->assetUrl(html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));

                return $url !== null ? $match[1].'="'.e($url).'"' : $match[0];
            },
            $html,
        ) ?? $html;
    }

    /**
     * A link target a component may use: http(s), mailto: and tel: URLs, an in-page #hash, a site path (/…), a page
     * slug (with an optional ?query#hash) or an asset. Null for anything else (javascript:, data:, protocol-relative…).
     */
    public function safeHref(string $target, string $lang): ?string
    {
        $target = trim($target);

        if ($target === '' || preg_match('/[\x00-\x20\x7f<>"\'`\\\\]/', $target) === 1) {
            return null;
        }

        if (preg_match('/^(?:https?:\/\/[^\/?#]+|mailto:[^\/]|tel:\+?[0-9])/i', $target) === 1) {
            return $target;
        }

        if (preg_match('/^#[A-Za-z][A-Za-z0-9_-]*$/', $target) === 1) {
            return $target;
        }

        if (str_starts_with($target, 'asset:') || str_starts_with($target, 'assets/')) {
            return $this->assetUrl($target);
        }

        if (str_starts_with($target, '/') && ! str_starts_with($target, '//') && ! str_contains($target, '..')) {
            return $target;
        }

        if (preg_match('/^(?:('.FrontKit::LANGUAGE_PATTERN.')\/)?('.FrontKit::SLUG_PATTERN.')((?:\?[^#]*)?(?:#.*)?)$/', $target, $match) !== 1) {
            return null;
        }

        $pageLang = $match[1] !== '' ? $match[1] : $lang;

        return $this->page($match[2], $pageLang) !== null ? $this->url($match[2], $pageLang).$this->safeSuffix($match[3]) : null;
    }

    /**
     * Public URL of a company asset ("assets/…" or "asset:…") with a content-hash query, or null when it is not served.
     */
    public function assetUrl(string $reference): ?string
    {
        $relative = $this->assets->normalize($reference);
        $resolved = $relative === null ? null : $this->assets->resolve($this->company(), $relative, $this->basePath);

        if ($resolved === null) {
            return null;
        }

        return $this->fileUrl('assets/'.$relative).'?v='.$this->assets->version($resolved);
    }

    /**
     * Intrinsic width and height of an image asset (SVG: width/height attributes or the viewBox), or null.
     *
     * @return array{0: int, 1: int}|null
     */
    public function assetSize(string $reference): ?array
    {
        $resolved = $this->assets->resolve($this->company(), $reference, $this->basePath);

        return $resolved !== null && $this->assets->isImage($resolved) ? $this->assets->size($resolved) : null;
    }

    public function assets(): FrontAssets
    {
        return $this->assets;
    }

    public function components(): FrontComponents
    {
        return new FrontComponents($this);
    }

    /**
     * The page as served to `Accept: text/markdown`: frontmatter and body, with component fences turned into
     * plain markdown (headings, lists, links) so no `:::` line reaches the reader.
     *
     * @param  array{lang?: string, frontmatter: array<string, mixed>, body: string}  $page
     */
    public function markdown(array $page): string
    {
        return FrontMatter::compose($page['frontmatter'], $this->bodyMarkdown($page));
    }

    /**
     * The page body as plain markdown (component fences rendered as readable markdown).
     *
     * @param  array{lang?: string, body: string}  $page
     */
    public function bodyMarkdown(array $page): string
    {
        return $this->components()->toMarkdown($page['body'], $page['lang'] ?? $this->defaultLanguage());
    }

    /**
     * Hero media from page frontmatter.hero: an image (any served image type; SVG sizes come from the file) or a muted
     * looping video with a poster, plus an optional kicker, caption and object position ("x% y%" only).
     *
     * @param  array{frontmatter: array<string, mixed>}  $page
     * @return array{image: ?string, width: ?int, height: ?int, mp4: ?string, webm: ?string, poster: ?string, alt: string, kicker: ?string, caption: ?string, position: ?string}|null
     */
    public function hero(array $page): ?array
    {
        $hero = $page['frontmatter']['hero'] ?? null;

        if (! is_array($hero)) {
            return null;
        }

        $asset = fn (string $key): ?string => is_string($hero[$key] ?? null) ? $this->assetUrl($hero[$key]) : null;
        $image = is_string($hero['image'] ?? null) && $this->assets->isImage($hero['image']) ? $asset('image') : null;
        $poster = is_string($hero['poster'] ?? null) && $this->assets->isImage($hero['poster']) ? $asset('poster') : null;
        $mp4 = is_string($hero['mp4'] ?? null) && str_ends_with(strtolower($hero['mp4']), '.mp4') ? $asset('mp4') : null;
        $webm = is_string($hero['webm'] ?? null) && str_ends_with(strtolower($hero['webm']), '.webm') ? $asset('webm') : null;

        if ($image === null && $poster === null && $mp4 === null && $webm === null) {
            return null;
        }

        $size = $image !== null ? $this->assetSize($hero['image']) : ($poster !== null ? $this->assetSize($hero['poster']) : null);
        $position = $this->text($hero['position'] ?? null);

        return [
            'image' => $image,
            'width' => $size[0] ?? null,
            'height' => $size[1] ?? null,
            'mp4' => $mp4,
            'webm' => $webm,
            'poster' => $poster,
            'alt' => $this->text($hero['alt'] ?? null) ?? '',
            'kicker' => $this->text($hero['kicker'] ?? null),
            'caption' => $this->text($hero['caption'] ?? null),
            'position' => $position !== null && preg_match(self::HERO_POSITION, $position) === 1 ? $position : null,
        ];
    }

    /**
     * On a home page with hero media, the markdown before the first `## ` heading or `:::` fence is the hero copy
     * (beside the media); the rest is the page body.
     *
     * @return array{0: string, 1: string}
     */
    public function splitHomeIntro(string $body): array
    {
        if (preg_match('/\A(.*?)(?=^## |^:::)/ms', $body, $match) === 1) {
            return [trim($match[1]), trim(substr($body, strlen($match[0])))];
        }

        return [trim($body), ''];
    }

    /**
     * The forms declared in facts.forms, by slug.
     *
     * @return array<string, array<string, mixed>>
     */
    public function declaredForms(): array
    {
        $forms = [];

        foreach (is_array($this->facts()['forms'] ?? null) ? $this->facts()['forms'] : [] as $form) {
            if (is_array($form) && is_string($form['slug'] ?? null) && preg_match('/^'.FrontKit::SLUG_PATTERN.'$/', $form['slug']) === 1) {
                $forms[$form['slug']] ??= $form;
            }
        }

        return $forms;
    }

    /**
     * One form from facts.forms, ready to render: an AIFormFlow embed when its status is `live` (and it has a client),
     * else a link to the hosted form when there is one. Null when the form is unknown or has neither.
     *
     * @return array{slug: string, form: string, name: string, title: ?string, lead: ?string, live: bool, hosted: ?string, client: ?string, theme: string}|null
     */
    public function form(string $slug, string $lang): ?array
    {
        $form = $this->declaredForms()[$slug] ?? null;

        if ($form === null) {
            return null;
        }

        $formId = $this->localizedText($form['form'] ?? null, $lang);
        $formId = $formId !== null && preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,99}$/', $formId) === 1 ? $formId : null;
        $hosted = $this->localizedText($form['hosted'] ?? null, $lang);
        $hosted = $hosted !== null && preg_match('/^https:\/\/[^\/?#\s"\'<>]+(?:[\/?#][^\s"\'<>]*)?$/', $hosted) === 1 ? $hosted : null;
        $client = $this->text($form['client'] ?? null) ?? $this->formClient($hosted);
        $client = $client !== null && preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,99}$/', $client) === 1 ? $client : null;
        $live = strtolower((string) $this->text($form['status'] ?? null)) === 'live' && $client !== null;

        if (! $live && $hosted === null) {
            return null;
        }

        $theme = $this->text($form['theme'] ?? null);

        return [
            'slug' => $slug,
            'form' => $formId ?? $slug,
            'name' => $this->localizedText($form['name'] ?? null, $lang) ?? Str::headline($slug),
            'title' => $this->localizedText($form['title'] ?? null, $lang),
            'lead' => $this->localizedText($form['lead'] ?? null, $lang),
            'live' => $live,
            'hosted' => $hosted,
            'client' => $client,
            'theme' => $theme !== null && in_array($theme, ['light', 'dark', 'auto'], true) ? $theme : 'light',
        ];
    }

    /**
     * The forms a page names in frontmatter.forms, rendered after the article, minus those its body already
     * places with an inline `:::form` fence.
     *
     * @param  array{lang: string, frontmatter: array<string, mixed>, body: string}  $page
     * @return list<array{slug: string, form: string, name: string, title: ?string, lead: ?string, live: bool, hosted: ?string, client: ?string, theme: string}>
     */
    public function embeds(array $page): array
    {
        $inline = $this->components()->formSlugs($page['body']);
        $embeds = [];

        foreach ($this->pageFormSlugs($page) as $slug) {
            $form = in_array($slug, $inline, true) ? null : $this->form($slug, $page['lang']);

            if ($form !== null) {
                $embeds[] = $form;
            }
        }

        return $embeds;
    }

    /**
     * True when the page shows at least one live AIFormFlow embed (after the article or inline), so form.js is needed.
     *
     * @param  array{lang: string, frontmatter: array<string, mixed>, body: string}  $page
     */
    public function hasLiveForm(array $page): bool
    {
        foreach (array_unique([...$this->pageFormSlugs($page), ...$this->components()->formSlugs($page['body'])]) as $slug) {
            if (($this->form($slug, $page['lang'])['live'] ?? false) === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{frontmatter: array<string, mixed>}  $page
     * @return list<string>
     */
    public function pageFormSlugs(array $page): array
    {
        return array_values(array_unique(array_filter(
            (array) ($page['frontmatter']['forms'] ?? []),
            fn (mixed $slug): bool => is_string($slug) && preg_match('/^'.FrontKit::SLUG_PATTERN.'$/', $slug) === 1,
        )));
    }

    /**
     * Self-hosted web fonts from facts.fonts.faces (woff2 files under assets/). Nothing loads from a font CDN.
     *
     * @return array{faces: list<array{family: string, url: string, weight: string, style: string, stretch: string}>, preload: ?string}
     */
    public function fonts(): array
    {
        $declared = $this->facts()['fonts'] ?? [];
        $faces = [];
        $preload = null;

        foreach (is_array($declared) && is_array($declared['faces'] ?? null) ? $declared['faces'] : [] as $face) {
            if (! is_array($face)) {
                continue;
            }

            $family = $this->text($face['family'] ?? null);
            $url = is_string($face['src'] ?? null) && str_ends_with(strtolower($face['src']), '.woff2') ? $this->assetUrl($face['src']) : null;

            if ($family === null || $url === null || preg_match('/^[\p{L}\p{N} _-]{1,64}$/u', $family) !== 1) {
                continue;
            }

            $faces[] = [
                'family' => $family,
                'url' => $url,
                'weight' => $this->cssToken($face['weight'] ?? null, '400'),
                'style' => $this->cssToken($face['style'] ?? null, 'normal'),
                'stretch' => $this->cssToken($face['stretch'] ?? null, 'normal'),
            ];

            if (($face['preload'] ?? $face['primary'] ?? false) === true) {
                $preload ??= $url;
            }
        }

        return ['faces' => $faces, 'preload' => $preload];
    }

    /**
     * Scripts from facts.scripts: company files under assets/ (.js) and AIFormFlow URLs only. A row with
     * `enabled: false` is skipped.
     *
     * @return list<array{src: string, defer: bool, module: bool}>
     */
    public function scripts(): array
    {
        $scripts = [];

        foreach (is_array($this->facts()['scripts'] ?? null) ? $this->facts()['scripts'] : [] as $script) {
            $script = is_string($script) ? ['src' => $script] : $script;

            if (! is_array($script) || ($script['enabled'] ?? true) === false) {
                continue;
            }

            $src = $this->scriptSrc($script['src'] ?? null);

            if ($src !== null) {
                $scripts[] = [
                    'src' => $src,
                    'defer' => ($script['defer'] ?? true) !== false,
                    'module' => ($script['module'] ?? false) === true,
                ];
            }
        }

        return $scripts;
    }

    /**
     * An allowed script URL: an assets/…js file, or https on an AIFormFlow host. Null for anything else.
     */
    public function scriptSrc(mixed $src): ?string
    {
        $src = $this->text($src);

        if ($src === null) {
            return null;
        }

        if (str_starts_with($src, 'assets/') || str_starts_with($src, 'asset:')) {
            return str_ends_with(strtolower($src), '.js') ? $this->assetUrl($src) : null;
        }

        return self::isFormHostUrl($src) ? $src : null;
    }

    /**
     * True for an https URL on an AIFormFlow host: one of FORM_HOSTS or a single subdomain label under it
     * (forms.aiformflow.com, not a.b.aiformflow.com), the same hosts the CSP `https://*.<host>` source matches.
     */
    public static function isFormHostUrl(string $url): bool
    {
        if (preg_match('/^https:\/\/([a-z0-9.-]+)(?:[\/?#][^\s"\'<>]*)?$/i', $url, $match) !== 1) {
            return false;
        }

        $host = strtolower($match[1]);

        foreach (self::FORM_HOSTS as $allowed) {
            if ($host === $allowed || preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.'.preg_quote($allowed, '/').'$/', $host) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * The company's component CSS (theme/components.css), safe to inline in a <style> element: url() is kept only
     * for assets (rewritten to their URL), @import is dropped (FrontComponentsCss).
     */
    public function componentsCss(): string
    {
        $css = $this->kit->componentsCss($this->company(), $this->basePath);

        return $css === null ? '' : (new FrontComponentsCss)->inline($css, fn (string $reference): ?string => $this->assetUrl($reference));
    }

    /**
     * Logo from facts.logo (or facts.header.logo): `src` (or `light`), optional `dark`, `alt`. Null keeps the text brand.
     *
     * @return array{src: string, dark: ?string, alt: string, width: ?int, height: ?int}|null
     */
    public function logo(): ?array
    {
        $declared = $this->facts()['logo'] ?? $this->facts()['header']['logo'] ?? null;
        $declared = is_string($declared) ? ['src' => $declared] : $declared;

        if (! is_array($declared)) {
            return null;
        }

        $reference = $declared['src'] ?? $declared['light'] ?? null;
        $src = is_string($reference) && $this->assets->isImage($reference) ? $this->assetUrl($reference) : null;

        if ($src === null) {
            return null;
        }

        $size = $this->assetSize($reference);

        return [
            'src' => $src,
            'dark' => is_string($declared['dark'] ?? null) && $this->assets->isImage($declared['dark']) ? $this->assetUrl($declared['dark']) : null,
            'alt' => $this->text($declared['alt'] ?? null) ?? $this->name(),
            'width' => $size[0] ?? null,
            'height' => $size[1] ?? null,
        ];
    }

    /**
     * The header call to action from facts.cta (`label`, `target`, localized per language). A target `#form-<slug>`
     * stays on the page when the page shows that form, else points at the first page that does. Null keeps the
     * kit's Call button.
     *
     * @param  array{slug: string, lang: string, frontmatter: array<string, mixed>, body: string}  $page
     * @return array{label: string, href: string}|null
     */
    public function cta(array $page): ?array
    {
        $declared = $this->facts()['cta'] ?? null;

        if (! is_array($declared)) {
            return null;
        }

        $lang = $page['lang'];
        $label = $this->localizedText($declared['label'] ?? null, $lang);
        $target = $this->localizedText($declared['target'] ?? $declared['href'] ?? null, $lang);

        if ($label === null || $target === null) {
            return null;
        }

        if (preg_match('/^#form-('.FrontKit::SLUG_PATTERN.')$/', $target, $match) === 1) {
            return ['label' => $label, 'href' => $this->formHref($match[1], $page)];
        }

        $href = $this->safeHref($target, $lang);

        return $href === null ? null : ['label' => $label, 'href' => $href];
    }

    /**
     * Footer links from facts.footer.legal (privacy, terms…): each a page slug or a URL, with a label.
     *
     * @return list<array{label: string, url: string}>
     */
    public function legalLinks(string $lang): array
    {
        return $this->links($this->facts()['footer']['legal'] ?? [], $lang);
    }

    /**
     * Footer columns from facts.footer.columns (`title` + `links`), shown before the kit's contact column.
     *
     * @return list<array{title: string, links: list<array{label: string, url: string}>}>
     */
    public function footerColumns(string $lang): array
    {
        $columns = [];

        foreach (is_array($this->facts()['footer']['columns'] ?? null) ? $this->facts()['footer']['columns'] : [] as $column) {
            if (! is_array($column)) {
                continue;
            }

            $title = $this->localizedText($column['title'] ?? null, $lang);
            $links = $this->links($column['links'] ?? [], $lang);

            if ($title !== null || $links !== []) {
                $columns[] = ['title' => $title ?? '', 'links' => $links];
            }
        }

        return $columns;
    }

    /**
     * Well-known icons that exist under assets/icons/, keyed by their front-root file name.
     *
     * @return array<string, string>
     */
    public function icons(): array
    {
        $icons = [];

        foreach (self::WELL_KNOWN_ICONS as $name => $path) {
            if ($this->assets->resolve($this->company(), $path, $this->basePath) !== null) {
                $icons[$name] = $this->fileUrl($name);
            }
        }

        return $icons;
    }

    public function phoneHref(): ?string
    {
        $phone = $this->phone();

        return $phone !== null ? preg_replace('/[^\d+]/', '', $phone) : null;
    }

    /**
     * Everything the page view needs.
     *
     * @param  array{slug: string, lang: string, path: string, frontmatter: array<string, mixed>, body: string}  $page
     * @return array<string, mixed>
     */
    public function viewData(array $page, ?string $cspNonce = null): array
    {
        $lang = $page['lang'];
        $frontmatter = $page['frontmatter'];
        $layout = $frontmatter['layout'] ?? 'page';
        $layout = in_array($layout, self::LAYOUTS, true) ? $layout : 'page';
        $canonical = $this->text($frontmatter['canonical'] ?? null);
        $phone = $this->phone();
        $hero = $this->hero($page);
        $heroHtml = '';

        if ($layout === 'home' && $hero !== null) {
            [$intro, $rest] = $this->splitHomeIntro($page['body']);
            $heroHtml = $this->bodyHtml([...$page, 'body' => $intro]);
            $html = $this->bodyHtml([...$page, 'body' => $rest]);
        } else {
            $html = $this->bodyHtml($page);
        }

        return [
            'lang' => $lang,
            'title' => $this->text($frontmatter['title'] ?? null) ?? $this->name(),
            'description' => $this->text($frontmatter['description'] ?? null) ?? '',
            'noindex' => ! $this->isIndexable($page),
            'layout' => $layout,
            'canonical' => $canonical !== null && Str::startsWith($canonical, ['https://', 'http://']) ? $canonical : $this->url($page['slug'], $lang),
            'alternates' => $this->alternates($page['slug']),
            'tokens' => $this->tokensCss(),
            'componentsCss' => $this->componentsCss(),
            'html' => $html,
            'heroHtml' => $heroHtml,
            'hero' => $hero,
            't' => $this->strings($lang),
            'name' => $this->name(),
            'legalName' => $this->legalName() ?? $this->name(),
            'homeUrl' => $this->url('home', $lang),
            'logo' => $this->logo(),
            'cta' => $this->cta($page),
            'navigation' => $this->navigation($lang, $page['slug']),
            'languageLinks' => $this->languageLinks($page['slug'], $lang),
            'phone' => $phone,
            'phoneHref' => $this->phoneHref(),
            'email' => $this->email(),
            'address' => $this->publishesAddress() && $this->address() !== null ? implode(', ', $this->address()) : null,
            'cities' => $this->cities(),
            'services' => array_column($this->services($lang), 'name'),
            'hours' => $this->localizedHours($lang),
            'social' => $this->social(),
            'footerColumns' => $this->footerColumns($lang),
            'legalLinks' => $this->legalLinks($lang),
            'icons' => $this->icons(),
            'fonts' => $this->fonts(),
            'embeds' => $this->embeds($page),
            'embedScript' => $this->hasLiveForm($page) ? self::EMBED_SCRIPT : null,
            'scripts' => $this->scripts(),
            'cspNonce' => $cspNonce,
            'year' => now()->year,
        ];
    }

    /**
     * The Content-Security-Policy sent with a page: scripts only from the site, the page's nonce and AIFormFlow;
     * frames and connections only to AIFormFlow; no plugins, no foreign base URL.
     */
    public static function contentSecurityPolicy(string $nonce): string
    {
        $forms = [];

        foreach (self::FORM_HOSTS as $host) {
            array_push($forms, 'https://'.$host, 'https://*.'.$host);
        }

        $forms = implode(' ', $forms);

        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}' {$forms}",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: {$forms}",
            "font-src 'self'",
            "media-src 'self'",
            "frame-src {$forms}",
            "connect-src 'self' {$forms}",
            "form-action 'self' {$forms}",
            "base-uri 'self'",
            "object-src 'none'",
        ]);
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

    /**
     * A text that may be given once or per language ({en: …, es: …}).
     */
    public function localizedText(mixed $value, string $lang): ?string
    {
        if (is_array($value)) {
            $value = $value[$lang] ?? $value[$this->defaultLanguage()] ?? reset($value);
        }

        return $this->text($value);
    }

    /**
     * Where a `#form-<slug>` link goes from this page: the hash alone when the page shows the form, else the first
     * page in the language that does (falling back to the home page).
     *
     * @param  array{slug: string, lang: string, frontmatter: array<string, mixed>, body: string}  $page
     */
    public function formHref(string $slug, array $page): string
    {
        $hash = '#form-'.$slug;
        $shows = fn (array $candidate): bool => in_array($slug, [...$this->pageFormSlugs($candidate), ...$this->components()->formSlugs($candidate['body'])], true);

        if ($shows($page)) {
            return $hash;
        }

        foreach ($this->pages() as $candidate) {
            if ($candidate['lang'] === $page['lang'] && $shows($candidate)) {
                return $this->url($candidate['slug'], $candidate['lang']).$hash;
            }
        }

        return $this->url('home', $page['lang']).$hash;
    }

    /**
     * Keep a ?query and #hash made of plain characters; drop anything else.
     */
    private function safeSuffix(string $suffix): string
    {
        return $suffix === '' || preg_match('/^(?:\?[A-Za-z0-9_\-=&%.+]*)?(?:#[A-Za-z0-9_\-]*)?$/', $suffix) === 1 ? $suffix : '';
    }

    /**
     * Links from facts (a list of `slug`, or of {label, href|slug}).
     *
     * @return list<array{label: string, url: string}>
     */
    private function links(mixed $links, string $lang): array
    {
        $resolved = [];

        foreach (is_array($links) ? $links : [] as $entry) {
            $entry = is_string($entry) ? ['slug' => $entry] : $entry;

            if (! is_array($entry)) {
                continue;
            }

            $target = $this->localizedText($entry['href'] ?? $entry['url'] ?? $entry['slug'] ?? null, $lang);
            $url = $target !== null ? $this->safeHref($target, $lang) : null;
            $page = $target !== null ? $this->page($target, $lang) : null;
            $label = $this->localizedText($entry['label'] ?? null, $lang)
                ?? ($page !== null ? ($this->text($page['frontmatter']['nav_label'] ?? null) ?? $this->text($page['frontmatter']['title'] ?? null)) : null);

            if ($url !== null && $label !== null) {
                $resolved[] = ['label' => $label, 'url' => $url];
            }
        }

        return $resolved;
    }

    private function cssToken(mixed $value, string $default): string
    {
        $value = $this->text($value);

        return $value !== null && preg_match('/^[A-Za-z0-9.% -]{1,40}$/', $value) === 1 ? $value : $default;
    }

    /**
     * The AIFormFlow client of a hosted form URL on <client>.aiff.dev, or null.
     */
    private function formClient(?string $hosted): ?string
    {
        $host = $hosted !== null ? parse_url($hosted, PHP_URL_HOST) : null;

        if (! is_string($host) || ! str_ends_with(strtolower($host), '.aiff.dev')) {
            return null;
        }

        $client = substr(strtolower($host), 0, -strlen('.aiff.dev'));

        return $client !== '' && ! str_contains($client, '.') ? $client : null;
    }
}
