<?php

namespace App\Core\Front;

use InvalidArgumentException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Reads a company front from {base}/fronts/{company}: facts.yaml, pages/<slug>.<lang>.md,
 * skills/<name>.md, theme/tokens.css and lang/<lang>.yaml. The base path defaults to
 * config('agents.paths.repo'); a build passes its worktree. Files are only read when they
 * are regular files inside the front folder (no symlinks, no traversal).
 */
class FrontKit
{
    public const SLUG_PATTERN = '[a-z0-9]+(?:-[a-z0-9]+)*';

    public const LANGUAGE_PATTERN = '[a-z]{2}';

    private const COMPANY_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/';

    private const SKILL_PATTERN = '/^[a-z0-9][a-z0-9_-]{0,63}$/';

    public function exists(string $company, ?string $basePath = null): bool
    {
        if (! $this->isValidCompany($company)) {
            return false;
        }

        $directory = $this->directory($company, $basePath);

        return ! is_link($directory) && is_dir($directory);
    }

    public function directory(string $company, ?string $basePath = null): string
    {
        if (! $this->isValidCompany($company)) {
            throw new InvalidArgumentException("Invalid company slug [{$company}].");
        }

        return rtrim($basePath ?? (string) config('agents.paths.repo'), '/').'/'.$this->relativeDirectory($company);
    }

    public function factsYaml(string $company, ?string $basePath = null): ?string
    {
        return $this->read($company, 'facts.yaml', $basePath);
    }

    /**
     * @return array<string, mixed>
     */
    public function facts(string $company, ?string $basePath = null): array
    {
        return $this->parseYaml($this->factsYaml($company, $basePath));
    }

    /**
     * @return list<string>
     */
    public function neverWrite(string $company, ?string $basePath = null): array
    {
        $phrases = $this->facts($company, $basePath)['never_write'] ?? [];

        if (! is_array($phrases)) {
            return [];
        }

        $phrases = array_map(fn (mixed $phrase): string => is_string($phrase) ? trim($phrase) : '', $phrases);

        return array_values(array_unique(array_filter($phrases, fn (string $phrase): bool => $phrase !== '')));
    }

    /**
     * The site languages from facts.languages, default language first. Falls back to ['en'].
     *
     * @return list<string>
     */
    public function languages(string $company, ?string $basePath = null): array
    {
        $languages = $this->facts($company, $basePath)['languages'] ?? [];
        $languages = is_array($languages) ? $languages : [$languages];
        $languages = array_map(fn (mixed $language): string => is_string($language) ? strtolower(trim($language)) : '', $languages);
        $languages = array_values(array_unique(array_filter(
            $languages,
            fn (string $language): bool => preg_match('/^'.self::LANGUAGE_PATTERN.'$/', $language) === 1,
        )));

        return $languages !== [] ? $languages : ['en'];
    }

    /**
     * Every page, home first, then by slug and language order. `path` is relative to the base path.
     *
     * @return list<array{slug: string, lang: string, path: string, frontmatter: array<string, mixed>, body: string}>
     */
    public function pages(string $company, ?string $basePath = null): array
    {
        $pages = [];

        foreach ($this->files($company, 'pages', $basePath) as $file) {
            if (preg_match('/^('.self::SLUG_PATTERN.')\.('.self::LANGUAGE_PATTERN.')\.md$/', $file, $match) !== 1) {
                continue;
            }

            $page = $this->page($company, $match[1], $match[2], $basePath);

            if ($page !== null) {
                $pages[] = $page;
            }
        }

        $order = array_flip($this->languages($company, $basePath));

        usort($pages, fn (array $a, array $b): int => [$a['slug'] !== 'home', $a['slug'], $order[$a['lang']] ?? 99, $a['lang']]
            <=> [$b['slug'] !== 'home', $b['slug'], $order[$b['lang']] ?? 99, $b['lang']]);

        return $pages;
    }

    /**
     * @return array{slug: string, lang: string, path: string, frontmatter: array<string, mixed>, body: string}|null
     */
    public function page(string $company, string $slug, string $lang, ?string $basePath = null): ?array
    {
        if (preg_match('/^'.self::SLUG_PATTERN.'$/', $slug) !== 1 || preg_match('/^'.self::LANGUAGE_PATTERN.'$/', $lang) !== 1) {
            return null;
        }

        $relative = "pages/{$slug}.{$lang}.md";
        $contents = $this->read($company, $relative, $basePath);

        if ($contents === null) {
            return null;
        }

        $parsed = FrontMatter::parse($contents);

        return [
            'slug' => $slug,
            'lang' => $lang,
            'path' => $this->relativeDirectory($company).'/'.$relative,
            'frontmatter' => $parsed['frontmatter'],
            'body' => $parsed['body'],
        ];
    }

    /**
     * Skills by file name (skills/<name>.md) with the description from their frontmatter.
     *
     * @return list<array{name: string, description: string, path: string}>
     */
    public function skills(string $company, ?string $basePath = null): array
    {
        $skills = [];

        foreach ($this->files($company, 'skills', $basePath) as $file) {
            if (! str_ends_with($file, '.md')) {
                continue;
            }

            $name = substr($file, 0, -3);
            $contents = $this->skill($company, $name, $basePath);

            if ($contents === null) {
                continue;
            }

            $description = FrontMatter::parse($contents)['frontmatter']['description'] ?? '';

            $skills[] = [
                'name' => $name,
                'description' => is_string($description) ? trim($description) : '',
                'path' => $this->relativeDirectory($company).'/skills/'.$file,
            ];
        }

        return $skills;
    }

    /**
     * The full skill file (frontmatter included), or null when it does not exist.
     */
    public function skill(string $company, string $name, ?string $basePath = null): ?string
    {
        if (preg_match(self::SKILL_PATTERN, $name) !== 1) {
            return null;
        }

        return $this->read($company, "skills/{$name}.md", $basePath);
    }

    /**
     * The theme tokens (theme/tokens.css), or null when the front has none.
     */
    public function theme(string $company, ?string $basePath = null): ?string
    {
        return $this->read($company, 'theme/tokens.css', $basePath);
    }

    /**
     * The company's component CSS (theme/components.css), or null when the front has none.
     */
    public function componentsCss(string $company, ?string $basePath = null): ?string
    {
        return $this->read($company, 'theme/components.css', $basePath);
    }

    /**
     * UI strings from lang/<lang>.yaml ([] when missing or invalid).
     *
     * @return array<string, mixed>
     */
    public function uiStrings(string $company, string $lang, ?string $basePath = null): array
    {
        if (preg_match('/^'.self::LANGUAGE_PATTERN.'$/', $lang) !== 1) {
            return [];
        }

        return $this->parseYaml($this->read($company, "lang/{$lang}.yaml", $basePath));
    }

    /**
     * Read a file inside the front folder. Null when the front folder or the file is missing or a symlink, or the
     * file resolves outside the folder.
     */
    public function read(string $company, string $relativePath, ?string $basePath = null): ?string
    {
        if (! $this->exists($company, $basePath)) {
            return null;
        }

        $directory = realpath($this->directory($company, $basePath));

        if ($directory === false) {
            return null;
        }

        $path = $directory.'/'.ltrim($relativePath, '/');

        if (is_link($path) || ! is_file($path)) {
            return null;
        }

        $resolved = realpath($path);

        if ($resolved === false || ! str_starts_with($resolved, $directory.'/')) {
            return null;
        }

        $contents = file_get_contents($resolved);

        return $contents === false ? null : $contents;
    }

    /**
     * Names of the regular, non-hidden files in a sub-folder of the front, sorted.
     *
     * @return list<string>
     */
    public function files(string $company, string $subdirectory, ?string $basePath = null): array
    {
        if (! $this->exists($company, $basePath)) {
            return [];
        }

        $directory = $this->directory($company, $basePath).'/'.trim($subdirectory, '/');

        if (is_link($directory) || ! is_dir($directory)) {
            return [];
        }

        $files = array_values(array_filter(
            scandir($directory) ?: [],
            fn (string $file): bool => ! str_starts_with($file, '.') && is_file($directory.'/'.$file) && ! is_link($directory.'/'.$file),
        ));

        sort($files);

        return $files;
    }

    private function relativeDirectory(string $company): string
    {
        return trim((string) config('agents.paths.fronts', 'fronts'), '/').'/'.$company;
    }

    private function isValidCompany(string $company): bool
    {
        return preg_match(self::COMPANY_PATTERN, $company) === 1;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseYaml(?string $yaml): array
    {
        if ($yaml === null) {
            return [];
        }

        try {
            $parsed = Yaml::parse($yaml);
        } catch (ParseException) {
            return [];
        }

        return is_array($parsed) && ! array_is_list($parsed) ? $parsed : [];
    }
}
