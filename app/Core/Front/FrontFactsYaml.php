<?php

namespace App\Core\Front;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Writes structured changes into a front's facts.yaml. Keys the editor does not know are kept, and so is the
 * comment block at the top of the file; other comments and the original formatting are not.
 */
class FrontFactsYaml
{
    /**
     * @param  array<string, mixed>  $changes  top-level keys to set; null removes a key. `discovery` is merged into the
     *                                         existing mapping, and each service keeps the extra keys of the entry with the same slug.
     *
     * @throws ParseException when the current facts.yaml is not a valid YAML mapping
     */
    public function merge(?string $yaml, array $changes): string
    {
        $facts = $this->parse($yaml);

        foreach ($changes as $key => $value) {
            if ($value === null) {
                unset($facts[$key]);

                continue;
            }

            $facts[$key] = match ($key) {
                'discovery' => [...(is_array($facts['discovery'] ?? null) ? $facts['discovery'] : []), ...(array) $value],
                'services' => $this->mergeServices($facts['services'] ?? [], (array) $value),
                default => $value,
            };
        }

        return $this->header($yaml).Yaml::dump(
            $facts,
            4,
            2,
            Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK | Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE | Yaml::DUMP_COMPACT_NESTED_MAPPING,
        );
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ParseException
     */
    private function parse(?string $yaml): array
    {
        if ($yaml === null || trim($yaml) === '') {
            return [];
        }

        $facts = Yaml::parse($yaml);

        if ($facts === null) {
            return [];
        }

        if (! is_array($facts) || (array_is_list($facts) && $facts !== [])) {
            throw new ParseException('facts.yaml must be a YAML mapping (key: value lines).');
        }

        return $facts;
    }

    /**
     * @param  list<array<string, mixed>>  $services
     * @return list<array<string, mixed>>
     */
    private function mergeServices(mixed $existing, array $services): array
    {
        $bySlug = [];

        foreach (is_array($existing) ? $existing : [] as $service) {
            if (is_array($service) && is_string($service['slug'] ?? null)) {
                $bySlug[$service['slug']] = $service;
            }
        }

        return array_values(array_map(
            fn (array $service): array => [...($bySlug[$service['slug'] ?? ''] ?? []), ...$service],
            $services,
        ));
    }

    /**
     * The comment and blank lines at the very top of the file.
     */
    private function header(?string $yaml): string
    {
        $header = '';

        foreach (preg_split('/(?<=\n)/', str_replace("\r\n", "\n", (string) $yaml)) ?: [] as $line) {
            if (preg_match('/^[ \t]*(?:#.*)?\n?$/', $line) !== 1 || $line === '') {
                break;
            }

            $header .= $line;
        }

        return $header === '' || str_ends_with($header, "\n") ? $header : $header."\n";
    }
}
