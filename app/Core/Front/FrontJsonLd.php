<?php

namespace App\Core\Front;

/**
 * Structured data for front pages: LocalBusiness from facts (never a rating, never a street
 * address unless facts.address.publish), a Service per service the page names, and the
 * page's own frontmatter `jsonld` with rating, review and certification claims removed.
 */
class FrontJsonLd
{
    /**
     * Keys that would claim ratings, reviews, awards or certifications (compared lowercase).
     */
    private const FORBIDDEN_KEYS = [
        'aggregaterating',
        'review',
        'reviews',
        'reviewrating',
        'rating',
        'award',
        'awards',
        'certification',
        'hascertification',
        'hascredential',
    ];

    /**
     * Types that make the same claims (compared lowercase).
     */
    private const FORBIDDEN_TYPES = [
        'aggregaterating',
        'review',
        'rating',
        'certification',
        'educationaloccupationalcredential',
    ];

    /**
     * Every JSON-LD block for a page, encoded and safe to print inside a script element.
     *
     * @param  array{slug: string, lang: string, path: string, frontmatter: array<string, mixed>, body: string}  $page
     * @return list<string>
     */
    public function scripts(FrontSite $site, array $page): array
    {
        return array_map(fn (array $block): string => $this->encode($block), $this->blocks($site, $page));
    }

    /**
     * @param  array{slug: string, lang: string, path: string, frontmatter: array<string, mixed>, body: string}  $page
     * @return list<array<string, mixed>>
     */
    public function blocks(FrontSite $site, array $page): array
    {
        $blocks = [$this->localBusiness($site, $page['lang'])];

        foreach ($this->pageServices($page) as $slug) {
            $name = $site->serviceName($slug, $page['lang']);

            if ($name !== null) {
                $blocks[] = $this->filled([
                    '@context' => 'https://schema.org',
                    '@type' => 'Service',
                    'name' => $name,
                    'serviceType' => $name,
                    'url' => $site->url($page['slug'], $page['lang']),
                    'provider' => ['@id' => $this->businessId($site)],
                    'areaServed' => $this->cities($site->cities()),
                ]);
            }
        }

        $custom = $page['frontmatter']['jsonld'] ?? null;

        if (is_array($custom) && $custom !== []) {
            foreach (array_is_list($custom) ? $custom : [$custom] as $block) {
                $block = is_array($block) ? $this->sanitize($block) : null;

                if (is_array($block) && $block !== []) {
                    $blocks[] = ['@context' => 'https://schema.org', ...$block];
                }
            }
        }

        return $blocks;
    }

    /**
     * @return array<string, mixed>
     */
    public function localBusiness(FrontSite $site, string $lang): array
    {
        $address = $site->address();

        return $this->filled([
            '@context' => 'https://schema.org',
            '@type' => 'LocalBusiness',
            '@id' => $this->businessId($site),
            'name' => $site->name(),
            'legalName' => $site->legalName(),
            'url' => $site->url('home', $lang),
            'telephone' => $site->phone(),
            'email' => $site->email(),
            'address' => $address !== null ? ['@type' => 'PostalAddress', ...$address] : null,
            'areaServed' => $this->cities($site->cities()),
            'knowsLanguage' => $site->languages(),
            'openingHours' => $site->hours(),
            'sameAs' => array_column($site->social(), 'url'),
            'makesOffer' => array_map(fn (array $service): array => [
                '@type' => 'Offer',
                'itemOffered' => ['@type' => 'Service', 'name' => $service['name']],
            ], $site->services($lang)),
        ]);
    }

    /**
     * Rating, review and certification claims found in a frontmatter `jsonld` value.
     *
     * @return list<array{claim: string, path: string}>
     */
    public function forbiddenClaims(mixed $jsonld, string $path = 'jsonld'): array
    {
        if (! is_array($jsonld)) {
            return [];
        }

        $claims = [];

        foreach ($jsonld as $key => $value) {
            $location = $path.'.'.$key;

            if (is_string($key) && in_array(strtolower($key), self::FORBIDDEN_KEYS, true)) {
                $claims[] = ['claim' => $key, 'path' => $location];

                continue;
            }

            if ($key === '@type' && $this->hasForbiddenType($value)) {
                $claims[] = ['claim' => implode(', ', array_filter((array) $value, 'is_string')), 'path' => $location];

                continue;
            }

            array_push($claims, ...$this->forbiddenClaims($value, $location));
        }

        return $claims;
    }

    /**
     * Remove every forbidden key and every object of a forbidden type.
     *
     * @param  array<array-key, mixed>  $jsonld
     * @return array<array-key, mixed>|null
     */
    public function sanitize(array $jsonld): ?array
    {
        if ($this->hasForbiddenType($jsonld['@type'] ?? null)) {
            return null;
        }

        $clean = [];

        foreach ($jsonld as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::FORBIDDEN_KEYS, true)) {
                continue;
            }

            if (is_array($value)) {
                $value = $this->sanitize($value);

                if ($value === null) {
                    continue;
                }
            }

            $clean[$key] = $value;
        }

        return array_is_list($jsonld) ? array_values($clean) : $clean;
    }

    /**
     * JSON for a script element: `<`, `>` and `&` are escaped so the text cannot close the element.
     *
     * @param  array<string, mixed>  $block
     */
    public function encode(array $block): string
    {
        return (string) json_encode(
            $block,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE,
        );
    }

    private function businessId(FrontSite $site): string
    {
        return $site->url('home', $site->defaultLanguage()).'#business';
    }

    /**
     * @param  array{frontmatter: array<string, mixed>}  $page
     * @return list<string>
     */
    private function pageServices(array $page): array
    {
        $services = [$page['frontmatter']['service'] ?? null, ...(array) ($page['frontmatter']['services'] ?? [])];

        return array_values(array_unique(array_filter($services, fn (mixed $slug): bool => is_string($slug) && $slug !== '')));
    }

    /**
     * @param  list<string>  $cities
     * @return list<array{'@type': string, name: string}>
     */
    private function cities(array $cities): array
    {
        return array_map(fn (string $city): array => ['@type' => 'City', 'name' => $city], $cities);
    }

    private function hasForbiddenType(mixed $type): bool
    {
        foreach ((array) $type as $value) {
            if (is_string($value) && in_array(strtolower($value), self::FORBIDDEN_TYPES, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Drop null and empty values.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function filled(array $values): array
    {
        return array_filter($values, fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []);
    }
}
