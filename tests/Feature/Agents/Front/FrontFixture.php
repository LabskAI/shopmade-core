<?php

namespace Tests\Feature\Agents\Front;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;

/**
 * Builds a throwaway base path with fronts/<company>/… and points the front kit at it
 * (config agents.paths.repo + agents.company), so tests never read or write the real fronts/.
 */
class FrontFixture
{
    /**
     * @var list<string>
     */
    private static array $directories = [];

    /**
     * @param  array<string, string|null>  $files  paths relative to fronts/<company>; null drops a default file
     */
    public static function create(array $files = [], string $company = 'acme'): string
    {
        $base = self::temporaryDirectory();
        self::write($base.'/fronts/'.$company, array_replace(self::defaults(), $files));

        config([
            'agents.paths.repo' => $base,
            'agents.company' => $company,
        ]);

        return $base;
    }

    /**
     * An empty directory under the system temp dir, removed by cleanup().
     */
    public static function temporaryDirectory(): string
    {
        $directory = sys_get_temp_dir().'/shopmade-front-'.Str::lower(Str::random(12));
        File::ensureDirectoryExists($directory);
        self::$directories[] = $directory;

        return $directory;
    }

    /**
     * @param  array<string, string|null>  $files  relative path => contents; null entries are skipped
     */
    public static function write(string $directory, array $files): void
    {
        foreach ($files as $path => $contents) {
            if ($contents === null) {
                continue;
            }

            File::ensureDirectoryExists(dirname($directory.'/'.$path));
            File::put($directory.'/'.$path, $contents);
        }
    }

    /**
     * A page file from frontmatter and a markdown body.
     *
     * @param  array<string, mixed>  $frontmatter
     */
    public static function page(array $frontmatter, string $body): string
    {
        return "---\n".Yaml::dump($frontmatter, 4, 2)."---\n\n".$body."\n";
    }

    /**
     * facts.yaml with some keys replaced.
     *
     * @param  array<string, mixed>  $overrides
     */
    public static function facts(array $overrides = []): string
    {
        return Yaml::dump(array_replace(self::defaultFacts(), $overrides), 4, 2);
    }

    public static function cleanup(): void
    {
        foreach (self::$directories as $directory) {
            File::deleteDirectory($directory);
        }

        self::$directories = [];
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultFacts(): array
    {
        return [
            'name' => 'Acme Cleaning',
            'legal_name' => 'Acme Cleaning LLC',
            'phone' => '+1 239-555-0100',
            'email' => 'hello@acme.example',
            'cities' => ['Naples', 'Estero'],
            'services' => [
                ['slug' => 'house-cleaning', 'name' => ['en' => 'House cleaning', 'es' => 'Limpieza de casas']],
                ['slug' => 'office-cleaning', 'name' => 'Office cleaning'],
            ],
            'languages' => ['en', 'es'],
            'hours' => ['Mo-Fr 08:00-18:00'],
            'never_write' => ['certified', 'OSHA', 'best in', '#1'],
            'social' => [],
            'discovery' => ['ai_search' => true, 'ai_training' => false],
            'address' => [
                'publish' => false,
                'street' => '100 Example Ave',
                'locality' => 'Naples',
                'region' => 'FL',
                'postal_code' => '34102',
                'country' => 'US',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function defaults(): array
    {
        return [
            'facts.yaml' => self::facts(),
            'pages/home.en.md' => self::page(
                ['title' => 'Acme Cleaning in Naples', 'description' => 'House and office cleaning in Naples and Estero.', 'layout' => 'home'],
                "# Clean homes in Naples\n\nWe clean houses and offices.\n\n[Our services](services)",
            ),
            'pages/home.es.md' => self::page(
                ['title' => 'Acme Cleaning en Naples', 'description' => 'Limpieza de casas y oficinas en Naples y Estero.', 'layout' => 'home'],
                "# Casas limpias en Naples\n\nLimpiamos casas y oficinas.\n\n[Nuestros servicios](services)",
            ),
            'pages/services.en.md' => self::page(
                ['title' => 'Cleaning services', 'description' => 'House cleaning and office cleaning.', 'nav_label' => 'Services', 'services' => ['house-cleaning'], 'cities' => ['Naples']],
                "# Cleaning services\n\nHouse cleaning every week.",
            ),
            'pages/services.es.md' => self::page(
                ['title' => 'Servicios de limpieza', 'description' => 'Limpieza de casas y de oficinas.', 'nav_label' => 'Servicios', 'services' => ['house-cleaning'], 'cities' => ['Naples']],
                "# Servicios de limpieza\n\nLimpieza de casas cada semana.",
            ),
            'lang/en.yaml' => "language_name: English\ncall: Call\n",
            'lang/es.yaml' => "language_name: Español\ncall: Llamar\nhome: Inicio\n",
            'theme/tokens.css' => ":root {\n    --color-accent: #123456;\n}\n",
            'skills/brand-voice.md' => "---\nname: brand-voice\ndescription: Plain and warm.\n---\n\n# Brand voice\n\nShort sentences.\n",
        ];
    }
}
