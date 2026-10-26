<?php

namespace App\Core\Front;

/**
 * Allowlisted component fences in page bodies. Raw HTML stays stripped: only the names below render, only through
 * the kit's Blade views (resources/views/front/components), and every attribute is text that is escaped on output.
 * `class` and `variant` keep lowercase tokens only, links go through FrontSite::safeHref, images and icons through
 * FrontSite::assetUrl. An unknown fence, or a known one without its closing `:::`, is left as plain text.
 *
 *     :::section variant="muted" kicker="What we do"
 *     ## Heading
 *     :::cards
 *     :::card title="Websites" icon="code"
 *     Copy
 *     :::
 *     :::
 *     :::
 *
 * Classes follow one contract a company's theme/components.css can style: `front-<name>` on every component,
 * `front-<name>--<variant>` for its variant, `front-<name>__<part>` for its parts, plus the page's `class=` tokens.
 */
class FrontComponents
{
    /**
     * Fence name => component (aliases included).
     *
     * @var array<string, string>
     */
    public const NAMES = [
        'section' => 'section',
        'cards' => 'cards',
        'card-grid' => 'cards',
        'card' => 'card',
        'stat' => 'stat',
        'faq' => 'faq',
        'accordion' => 'faq',
        'item' => 'item',
        'cta' => 'cta',
        'cta-band' => 'cta',
        'quote' => 'quote',
        'testimonial' => 'quote',
        'steps' => 'steps',
        'step' => 'step',
        'proof' => 'proof',
        'timeline' => 'timeline',
        'flow' => 'flow',
        'form' => 'form',
    ];

    /**
     * Attributes each component takes. Anything else is dropped (and reported by front:lint).
     *
     * @var array<string, list<string>>
     */
    public const ATTRIBUTES = [
        'section' => ['variant', 'class', 'id', 'kicker', 'image', 'alt'],
        'cards' => ['variant', 'class', 'id'],
        'card' => ['variant', 'class', 'id', 'title', 'href', 'icon', 'image', 'alt', 'image2', 'alt2', 'chips', 'chip_variant', 'button_label'],
        'stat' => ['variant', 'class', 'value', 'label'],
        'faq' => ['variant', 'class', 'id'],
        'item' => ['variant', 'class', 'question'],
        'cta' => ['variant', 'class', 'id', 'title', 'href', 'label', 'secondary_href', 'secondary_label', 'image'],
        'quote' => ['variant', 'class', 'author', 'role'],
        'steps' => ['variant', 'class', 'id'],
        'step' => ['variant', 'class', 'title', 'icon', 'chip', 'time', 'status', 'image', 'alt'],
        'proof' => ['variant', 'class', 'id', 'href', 'label'],
        'timeline' => ['variant', 'class', 'id', 'title', 'where', 'span', 'note'],
        'flow' => ['variant', 'class', 'id', 'label'],
        'form' => ['variant', 'class', 'slug'],
    ];

    /**
     * Parent => the child fences it collects into its items.
     *
     * @var array<string, list<string>>
     */
    public const CHILDREN = [
        'cards' => ['card'],
        'faq' => ['item'],
        'steps' => ['step'],
        'proof' => ['card'],
        'timeline' => ['stat', 'step'],
        'flow' => ['step'],
    ];

    private const HREF_ATTRIBUTES = ['href', 'secondary_href'];

    private const IMAGE_ATTRIBUTES = ['image', 'image2'];

    private const MAX_CHIPS = 6;

    private const MAX_DEPTH = 8;

    private const OPEN = '/^:::([a-z][a-z0-9-]*)((?:[ \t]+[^\n]*)?)\n/m';

    public function __construct(private FrontSite $site) {}

    /**
     * The body as HTML: allowlisted fences become kit components, everything else is sanitized markdown.
     */
    public function render(string $body, string $lang, int $depth = 0): string
    {
        $html = '';

        foreach ($this->split($this->normalize($body), $depth) as $segment) {
            $html .= $segment['type'] === 'text'
                ? $this->site->markdownHtml($segment['value'], $lang)
                : $this->renderBlock($segment['name'], $segment['raw'], $segment['inner'], $lang, $depth + 1);
        }

        return $html;
    }

    /**
     * The body as readable markdown for llms-full.txt and `Accept: text/markdown`: components become headings,
     * lists, quotes and links, and no `:::` line is left.
     */
    public function toMarkdown(string $body, string $lang, int $depth = 0): string
    {
        $parts = [];

        foreach ($this->split($this->normalize($body), $depth) as $segment) {
            $parts[] = $segment['type'] === 'text'
                ? (string) preg_replace('/^:::.*$\n?/m', '', $segment['value'])
                : $this->markdownBlock($segment['name'], $segment['raw'], $segment['inner'], $lang, $depth + 1);
        }

        return $this->join($parts);
    }

    /**
     * Fence names in a body that the kit does not render.
     *
     * @return list<string>
     */
    public function unknownNames(string $body): array
    {
        preg_match_all('/^:::([a-z][a-z0-9-]*)/m', $this->normalize($body), $matches);

        return array_values(array_unique(array_filter($matches[1], fn (string $name): bool => ! isset(self::NAMES[$name]))));
    }

    /**
     * Attributes a body gives a known fence that the fence does not take, as [fence, attribute] pairs.
     *
     * @return list<array{0: string, 1: string}>
     */
    public function unknownAttributes(string $body): array
    {
        preg_match_all('/^:::([a-z][a-z0-9-]*)([ \t]+[^\n]*)?$/m', $this->normalize($body), $matches, PREG_SET_ORDER);
        $unknown = [];

        foreach ($matches as $match) {
            $component = self::NAMES[$match[1]] ?? null;

            if ($component === null) {
                continue;
            }

            foreach (array_keys($this->parseAttributes($match[2] ?? '')) as $key) {
                if (! in_array($key, self::ATTRIBUTES[$component], true)) {
                    $unknown[$match[1].' '.$key] = [$match[1], $key];
                }
            }
        }

        return array_values($unknown);
    }

    /**
     * The form slugs a body places with `:::form slug="…"` fences.
     *
     * @return list<string>
     */
    public function formSlugs(string $body): array
    {
        preg_match_all('/^:::form([ \t]+[^\n]*)?$/m', $this->normalize($body), $matches);
        $slugs = [];

        foreach ($matches[1] as $raw) {
            $slug = $this->parseAttributes($raw)['slug'] ?? '';

            if (preg_match('/^'.FrontKit::SLUG_PATTERN.'$/', $slug) === 1) {
                $slugs[] = $slug;
            }
        }

        return array_values(array_unique($slugs));
    }

    /**
     * Asset references in component attributes (image, image2, icon="asset:…"), for front:lint.
     *
     * @return list<array{0: string, 1: string}> [fence attribute, reference]
     */
    public function assetReferences(string $body): array
    {
        preg_match_all('/^:::([a-z][a-z0-9-]*)([ \t]+[^\n]*)?$/m', $this->normalize($body), $matches, PREG_SET_ORDER);
        $references = [];

        foreach ($matches as $match) {
            if (! isset(self::NAMES[$match[1]])) {
                continue;
            }

            foreach ($this->parseAttributes($match[2] ?? '') as $key => $value) {
                if (in_array($key, self::IMAGE_ATTRIBUTES, true) || ($key === 'icon' && $this->isAssetReference($value))) {
                    $references[] = [$match[1].' '.$key, $value];
                }
            }
        }

        return $references;
    }

    private function normalize(string $body): string
    {
        return str_replace(["\r\n", "\r"], "\n", $body);
    }

    /**
     * Top-level text and fence segments. Past MAX_DEPTH every fence stays text.
     *
     * @return list<array{type: 'text', value: string}|array{type: 'block', name: string, raw: string, inner: string}>
     */
    private function split(string $body, int $depth): array
    {
        $segments = [];
        $offset = 0;
        $text = '';
        $length = strlen($body);

        while ($depth < self::MAX_DEPTH && $offset < $length && preg_match(self::OPEN, $body, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $start = $match[0][1];
            $name = $match[1][0];
            $innerStart = $start + strlen($match[0][0]);
            $end = $this->closingOffset($body, $innerStart);
            $text .= substr($body, $offset, $start - $offset);

            if ($end === null || ! isset(self::NAMES[$name])) {
                $text .= $match[0][0];
                $offset = $innerStart;

                continue;
            }

            if ($text !== '') {
                $segments[] = ['type' => 'text', 'value' => $text];
                $text = '';
            }

            $segments[] = ['type' => 'block', 'name' => $name, 'raw' => $match[2][0], 'inner' => substr($body, $innerStart, $end - $innerStart)];
            $offset = $this->afterLine($body, $end);
        }

        $text .= substr($body, $offset);

        if ($text !== '') {
            $segments[] = ['type' => 'text', 'value' => $text];
        }

        return $segments;
    }

    /**
     * Offset of the `:::` line that closes the fence opened just before $from, counting nested fences; null if none.
     */
    private function closingOffset(string $body, int $from): ?int
    {
        $depth = 1;
        $offset = $from;

        while (preg_match('/^:::([a-z][a-z0-9-]*)?[^\n]*$/m', $body, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $at = $match[0][1];
            $opens = ($match[1][0] ?? '') !== '';

            if (! $opens && trim(substr($match[0][0], 3)) !== '') {
                $offset = $at + strlen($match[0][0]);

                continue;
            }

            $depth += $opens ? 1 : -1;

            if ($depth === 0) {
                return $at;
            }

            $offset = $at + strlen($match[0][0]);
        }

        return null;
    }

    private function afterLine(string $body, int $at): int
    {
        $newline = strpos($body, "\n", $at);

        return $newline === false ? strlen($body) : $newline + 1;
    }

    /**
     * The child fences of a parent as items, and the parent's own text around them.
     *
     * @param  list<string>  $allowed
     * @return array{0: list<array{fence: string, name: string, raw: string, inner: string}>, 1: string}
     */
    private function children(string $inner, array $allowed): array
    {
        $items = [];
        $leftover = '';
        $offset = 0;

        while (preg_match(self::OPEN, $inner, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $start = $match[0][1];
            $innerStart = $start + strlen($match[0][0]);
            $end = $this->closingOffset($inner, $innerStart);
            $name = self::NAMES[$match[1][0]] ?? null;

            if ($end === null) {
                break;
            }

            $leftover .= substr($inner, $offset, $start - $offset);

            if ($name === null || ! in_array($name, $allowed, true)) {
                $leftover .= substr($inner, $start, $this->afterLine($inner, $end) - $start);
            } else {
                $items[] = ['fence' => $match[1][0], 'name' => $name, 'raw' => $match[2][0], 'inner' => substr($inner, $innerStart, $end - $innerStart)];
            }

            $offset = $this->afterLine($inner, $end);
        }

        return [$items, $leftover.substr($inner, $offset)];
    }

    private function renderBlock(string $fence, string $raw, string $inner, string $lang, int $depth): string
    {
        $name = self::NAMES[$fence];
        $attrs = $this->attributes($name, $raw, $lang);
        $items = [];

        if (isset(self::CHILDREN[$name])) {
            [$children, $inner] = $this->children($inner, self::CHILDREN[$name]);

            foreach ($children as $child) {
                $childAttrs = $this->attributes($child['name'], $child['raw'], $lang);
                $items[] = [
                    'name' => $child['name'],
                    'attrs' => $childAttrs,
                    'class' => $this->className($child['name'], $childAttrs),
                    'html' => $this->render($child['inner'], $lang, $depth + 1),
                ];
            }
        }

        $data = [
            'attrs' => $attrs,
            'class' => $this->className($name, $attrs),
            'html' => $this->render($inner, $lang, $depth),
            'items' => $items,
            'lang' => $lang,
            't' => $this->site->strings($lang),
            'phone' => $this->site->phone(),
            'phoneHref' => $this->site->phoneHref(),
        ];

        if ($name === 'form') {
            $form = isset($attrs['slug']) ? $this->site->form($attrs['slug'], $lang) : null;

            if ($form === null) {
                return '';
            }

            $data['embed'] = $form;
        }

        return view('front.components.'.$name, $data)->render();
    }

    private function markdownBlock(string $fence, string $raw, string $inner, string $lang, int $depth): string
    {
        $name = self::NAMES[$fence];
        $attrs = $this->attributes($name, $raw, $lang);
        $items = [];

        if (isset(self::CHILDREN[$name])) {
            [$children, $inner] = $this->children($inner, self::CHILDREN[$name]);

            foreach ($children as $child) {
                $items[] = [
                    'name' => $child['name'],
                    'attrs' => $this->attributes($child['name'], $child['raw'], $lang),
                    'markdown' => $this->toMarkdown($child['inner'], $lang, $depth + 1),
                ];
            }
        }

        $body = $this->toMarkdown($inner, $lang, $depth);

        return match ($name) {
            'section' => $this->join([$this->emphasis($attrs['kicker'] ?? null), $body, $this->image($attrs, 'image', 'alt')]),
            'cards', 'proof' => $this->join([
                $body,
                ...array_map(fn (array $item): string => $this->cardMarkdown($item['attrs'], $item['markdown']), $items),
                $this->link($attrs['label'] ?? null, $attrs['href'] ?? null),
            ]),
            'card' => $this->cardMarkdown($attrs, $body),
            'faq' => $this->join([
                $body,
                ...array_map(fn (array $item): string => $this->join([$this->heading(3, $item['attrs']['question'] ?? null), $item['markdown']]), $items),
            ]),
            'item' => $this->join([$this->heading(3, $attrs['question'] ?? null), $body]),
            'steps', 'flow' => $this->join([$body, $this->numbered($items)]),
            'step' => $this->join([$this->heading(3, $attrs['title'] ?? null), $body]),
            'cta' => $this->join([
                $this->heading(2, $attrs['title'] ?? null),
                $body,
                $this->link($attrs['label'] ?? $attrs['title'] ?? null, $attrs['href'] ?? null),
                $this->link($attrs['secondary_label'] ?? null, $attrs['secondary_href'] ?? null),
            ]),
            'quote' => $this->join([
                $body !== '' ? (string) preg_replace('/^/m', '> ', $body) : null,
                isset($attrs['author']) ? '> — '.$attrs['author'].(isset($attrs['role']) ? ', '.$attrs['role'] : '') : null,
            ]),
            'stat' => $this->join([$this->statLine($attrs), $body]),
            'timeline' => $this->join([
                $this->heading(3, $attrs['title'] ?? null),
                implode(' · ', array_filter([$attrs['where'] ?? null, $attrs['span'] ?? null])),
                ...array_map(fn (array $item): string => $item['name'] === 'stat'
                    ? '- '.$this->statLine($item['attrs'])
                    : $this->join(['- '.trim(implode(' ', array_filter([$item['attrs']['time'] ?? null, $item['attrs']['title'] ?? null, isset($item['attrs']['status']) ? '('.$item['attrs']['status'].')' : null]))), $item['markdown']]), $items),
                $body,
                $attrs['note'] ?? null,
            ]),
            'form' => $this->formMarkdown($attrs, $body, $lang),
            default => $body,
        };
    }

    /**
     * Allowed attributes of a component, decoded and made safe: tokens for class/variant/id, safe links, asset URLs
     * for images and asset icons, and `chips` split on `|`.
     *
     * @return array<string, mixed>
     */
    private function attributes(string $name, string $raw, string $lang): array
    {
        $attrs = array_intersect_key($this->parseAttributes($raw), array_flip(self::ATTRIBUTES[$name]));

        foreach (['class', 'variant', 'chip_variant'] as $key) {
            if (isset($attrs[$key])) {
                $attrs[$key] = $this->tokens($attrs[$key]);
            }
        }

        if (isset($attrs['id'])) {
            $attrs['id'] = explode(' ', $this->tokens($attrs['id']))[0];
        }

        foreach (self::HREF_ATTRIBUTES as $key) {
            if (isset($attrs[$key])) {
                $attrs[$key] = $this->site->safeHref($attrs[$key], $lang) ?? '';
            }
        }

        foreach (self::IMAGE_ATTRIBUTES as $key) {
            if (isset($attrs[$key])) {
                $reference = $attrs[$key];
                $attrs[$key] = $this->site->assets()->isImage($reference) ? ($this->site->assetUrl($reference) ?? '') : '';
                $size = $attrs[$key] !== '' ? $this->site->assetSize($reference) : null;

                if ($size !== null) {
                    [$attrs[$key.'_width'], $attrs[$key.'_height']] = $size;
                }
            }
        }

        if (isset($attrs['icon'])) {
            if ($this->isAssetReference($attrs['icon'])) {
                $src = $this->site->assets()->isImage($attrs['icon']) ? $this->site->assetUrl($attrs['icon']) : null;
                $attrs['icon_src'] = $src ?? '';
                unset($attrs['icon']);
            } elseif (! FrontIcons::has($attrs['icon'])) {
                unset($attrs['icon']);
            }
        }

        if (isset($attrs['chips'])) {
            $chips = array_values(array_filter(array_map(
                fn (string $chip): string => mb_substr(trim($chip), 0, 60),
                explode('|', $attrs['chips']),
            ), fn (string $chip): bool => $chip !== ''));
            $attrs['chips'] = array_slice($chips, 0, self::MAX_CHIPS);
        }

        if (isset($attrs['slug']) && preg_match('/^'.FrontKit::SLUG_PATTERN.'$/', $attrs['slug']) !== 1) {
            unset($attrs['slug']);
        }

        return array_filter($attrs, fn (mixed $value): bool => $value !== '' && $value !== []);
    }

    /**
     * key="value", key='value', key=value or a bare key (true), decoded to plain single-line text.
     *
     * @return array<string, string>
     */
    private function parseAttributes(string $raw): array
    {
        preg_match_all('/([a-z][a-z0-9_-]*)(?:=(?:"([^"]*)"|\'([^\']*)\'|([^\s"\']+)))?/', $raw, $matches, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL);
        $attrs = [];

        foreach ($matches as $match) {
            $value = $match[2] ?? $match[3] ?? $match[4] ?? 'true';
            $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $value = trim((string) preg_replace('/[\x00-\x1f\x7f]+/u', ' ', $value));
            $attrs[$match[1]] ??= mb_substr($value, 0, 500);
        }

        return $attrs;
    }

    private function isAssetReference(string $value): bool
    {
        return str_starts_with($value, 'asset:') || str_starts_with($value, 'assets/');
    }

    /**
     * Lowercase class tokens only (letters, digits, - and _), space separated.
     */
    private function tokens(string $value): string
    {
        $tokens = preg_split('/\s+/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return implode(' ', array_slice(array_values(array_filter(
            $tokens,
            fn (string $token): bool => preg_match('/^[a-z][a-z0-9_-]{0,63}$/', $token) === 1,
        )), 0, 8));
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function className(string $name, array $attrs): string
    {
        $classes = ['front-'.$name];

        foreach (explode(' ', (string) ($attrs['variant'] ?? '')) as $variant) {
            if ($variant !== '') {
                $classes[] = 'front-'.$name.'--'.$variant;
            }
        }

        if (isset($attrs['class'])) {
            $classes[] = $attrs['class'];
        }

        return implode(' ', $classes);
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function cardMarkdown(array $attrs, string $markdown): string
    {
        $title = $attrs['title'] ?? null;
        $href = $attrs['href'] ?? null;
        $linkTitle = $title !== null && $href !== null && ! isset($attrs['button_label']);

        return $this->join([
            $title === null ? null : '### '.($linkTitle ? '['.$this->escape($title).']('.$href.')' : $this->escape($title)),
            $this->emphasis(isset($attrs['chips']) ? implode(' · ', $attrs['chips']) : null),
            $markdown,
            $this->image($attrs, 'image', 'alt'),
            $this->image($attrs, 'image2', 'alt2'),
            isset($attrs['button_label']) ? $this->link($attrs['button_label'], $href) : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function formMarkdown(array $attrs, string $body, string $lang): string
    {
        $form = isset($attrs['slug']) ? $this->site->form($attrs['slug'], $lang) : null;

        if ($form === null) {
            return '';
        }

        return $this->join([
            $this->heading(3, $form['title'] ?? $form['name']),
            $form['lead'],
            $body,
            $this->link($this->site->strings($lang)['open_form'], $form['hosted']),
        ]);
    }

    /**
     * @param  list<array{name: string, attrs: array<string, mixed>, markdown: string}>  $items
     */
    private function numbered(array $items): string
    {
        $lines = [];

        foreach (array_values($items) as $index => $item) {
            $label = trim(implode(' ', array_filter([
                isset($item['attrs']['title']) ? '**'.$this->escape($item['attrs']['title']).'**' : null,
                isset($item['attrs']['chip']) ? '('.$this->escape($item['attrs']['chip']).')' : null,
                isset($item['attrs']['time']) ? '— '.$this->escape($item['attrs']['time']) : null,
            ])));
            $text = trim($item['markdown']);
            $entry = ($index + 1).'. '.$label;

            if ($text !== '') {
                $entry = rtrim($entry).($label === '' ? $this->indent($text, false) : "\n".$this->indent($text, true));
            }

            $lines[] = rtrim($entry);
        }

        return implode("\n", $lines);
    }

    private function indent(string $text, bool $firstLine): string
    {
        $indented = (string) preg_replace('/^(?=.)/m', '   ', $text);

        return $firstLine ? $indented : ltrim($indented);
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function statLine(array $attrs): string
    {
        return trim((isset($attrs['value']) ? '**'.$this->escape($attrs['value']).'** ' : '').$this->escape($attrs['label'] ?? ''));
    }

    private function heading(int $level, ?string $text): ?string
    {
        return $text === null ? null : str_repeat('#', $level).' '.$this->escape($text);
    }

    private function emphasis(?string $text): ?string
    {
        return $text === null || $text === '' ? null : '*'.$this->escape($text).'*';
    }

    private function link(?string $label, ?string $href): ?string
    {
        return $label === null || $href === null || $href === '' ? null : '['.$this->escape($label).']('.$href.')';
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function image(array $attrs, string $key, string $altKey): ?string
    {
        return isset($attrs[$key]) ? '!['.$this->escape($attrs[$altKey] ?? '').']('.$attrs[$key].')' : null;
    }

    /**
     * Attribute text in generated markdown: brackets and angle brackets are escaped so it stays text.
     */
    private function escape(string $text): string
    {
        return addcslashes($text, '[]<>*_`\\');
    }

    /**
     * @param  list<?string>  $parts
     */
    private function join(array $parts): string
    {
        return implode("\n\n", array_values(array_filter(
            array_map(fn (?string $part): string => trim((string) $part), $parts),
            fn (string $part): bool => $part !== '',
        )));
    }
}
