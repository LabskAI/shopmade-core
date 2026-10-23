<?php

namespace App\Core\Plugins;

use Illuminate\Support\Str;
use RuntimeException;

class Manifest
{
    /**
     * @param  list<array{label: string, route: string, roles: list<string>}>  $nav
     * @param  array{plugin: string, label: string, route: string}|null  $attach
     * @param  list<string>  $tables
     * @param  list<string>  $depends
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly string $path,
        public readonly string $slug,
        public readonly string $name,
        public readonly string $version,
        public readonly string $summary,
        public readonly string $provider,
        public readonly string $mount,
        public readonly bool $public,
        public readonly array $nav,
        public readonly ?array $attach,
        public readonly array $tables,
        public readonly array $depends,
        public readonly int $designSystem,
        public readonly array $raw,
    ) {}

    public function directory(): string
    {
        return dirname($this->path);
    }

    public static function fromFile(string $path): ?self
    {
        if (! is_file($path)) {
            return null;
        }

        try {
            $raw = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (! is_array($raw)) {
            return null;
        }

        try {
            return self::fromArray($raw, $path);
        } catch (RuntimeException) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function fromArray(array $raw, string $path): self
    {
        $folder = basename(dirname($path));
        $slug = $raw['slug'] ?? null;

        if (! is_string($slug) || preg_match('/^[a-z][a-z0-9-]{1,40}$/', $slug) !== 1) {
            throw new RuntimeException('Invalid slug.');
        }

        if ($slug !== $folder) {
            throw new RuntimeException('Slug must match the folder name.');
        }

        $provider = $raw['provider'] ?? null;
        $expectedProvider = 'Plugins\\'.Str::studly($slug).'\\ServiceProvider';

        if ($provider !== $expectedProvider) {
            throw new RuntimeException('Provider must be '.$expectedProvider.'.');
        }

        $mount = $raw['mount'] ?? null;
        $public = $raw['public'] ?? null;
        $expectedMount = $slug === 'site' ? '/' : '/p/'.$slug;

        if ($mount !== $expectedMount) {
            throw new RuntimeException('Mount must be '.$expectedMount.'.');
        }

        if (! is_bool($public) || ($public && $slug !== 'site')) {
            throw new RuntimeException('Only the site plugin may be public.');
        }

        if ($slug === 'site' && $public !== true) {
            throw new RuntimeException('The site plugin must be public.');
        }

        $nav = $raw['nav'] ?? null;

        if (! is_array($nav)) {
            throw new RuntimeException('nav must be an array.');
        }

        $normalizedNav = [];

        foreach ($nav as $entry) {
            if (! is_array($entry) || ! is_string($entry['label'] ?? null) || ! is_string($entry['route'] ?? null) || ! is_array($entry['roles'] ?? null)) {
                throw new RuntimeException('Invalid nav entry.');
            }

            $normalizedNav[] = [
                'label' => $entry['label'],
                'route' => $entry['route'],
                'roles' => array_values($entry['roles']),
            ];
        }

        $attach = $raw['attach'] ?? null;

        if ($attach !== null) {
            if (! is_array($attach) || ! is_string($attach['plugin'] ?? null) || ! is_string($attach['label'] ?? null) || ! is_string($attach['route'] ?? null)) {
                throw new RuntimeException('Invalid attach.');
            }

            $attach = [
                'plugin' => $attach['plugin'],
                'label' => $attach['label'],
                'route' => $attach['route'],
            ];
        }

        $tables = $raw['tables'] ?? null;
        $depends = $raw['depends'] ?? null;

        if (! is_array($tables) || ! is_array($depends)) {
            throw new RuntimeException('tables and depends must be arrays.');
        }

        foreach ($tables as $table) {
            if (! is_string($table) || ! str_starts_with($table, 'p_')) {
                throw new RuntimeException('Plugin tables must use the p_ prefix.');
            }
        }

        $name = $raw['name'] ?? null;
        $version = $raw['version'] ?? null;
        $summary = $raw['summary'] ?? null;
        $designSystem = $raw['design_system'] ?? null;

        if (! is_string($name) || $name === '' || ! is_string($version) || $version === '' || ! is_string($summary) || ! is_int($designSystem)) {
            throw new RuntimeException('Missing required manifest fields.');
        }

        return new self(
            path: $path,
            slug: $slug,
            name: $name,
            version: $version,
            summary: $summary,
            provider: $provider,
            mount: $mount,
            public: $public,
            nav: $normalizedNav,
            attach: $attach,
            tables: array_values($tables),
            depends: array_values($depends),
            designSystem: $designSystem,
            raw: $raw,
        );
    }
}
