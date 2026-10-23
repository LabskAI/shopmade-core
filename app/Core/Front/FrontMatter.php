<?php

namespace App\Core\Front;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class FrontMatter
{
    /**
     * Split a markdown file into its YAML frontmatter (between two `---` lines) and its body.
     * Never throws: a broken frontmatter comes back empty with the parser message in `error`.
     *
     * @return array{frontmatter: array<string, mixed>, body: string, error: ?string}
     */
    public static function parse(string $contents): array
    {
        $contents = str_replace(["\r\n", "\r"], "\n", $contents);

        if (str_starts_with($contents, "\xEF\xBB\xBF")) {
            $contents = substr($contents, 3);
        }

        if (preg_match('/\A---[ \t]*\n/', $contents) !== 1) {
            return ['frontmatter' => [], 'body' => trim($contents, "\n"), 'error' => null];
        }

        if (preg_match('/\A---[ \t]*\n(.*?)^(?:---|\.\.\.)[ \t]*$\n?(.*)\z/ms', $contents, $match) !== 1) {
            return ['frontmatter' => [], 'body' => trim($contents, "\n"), 'error' => 'The frontmatter has no closing --- line.'];
        }

        $body = trim($match[2], "\n");

        try {
            $frontmatter = Yaml::parse($match[1]);
        } catch (ParseException $exception) {
            return ['frontmatter' => [], 'body' => $body, 'error' => 'The frontmatter is not valid YAML: '.$exception->getMessage()];
        }

        if ($frontmatter === null) {
            return ['frontmatter' => [], 'body' => $body, 'error' => null];
        }

        if (! is_array($frontmatter) || (array_is_list($frontmatter) && $frontmatter !== [])) {
            return ['frontmatter' => [], 'body' => $body, 'error' => 'The frontmatter must be a YAML mapping (key: value lines).'];
        }

        return ['frontmatter' => $frontmatter, 'body' => $body, 'error' => null];
    }

    /**
     * Write frontmatter and body back as one markdown document (comments in the source are not kept).
     *
     * @param  array<string, mixed>  $frontmatter
     */
    public static function compose(array $frontmatter, string $body): string
    {
        if ($frontmatter === []) {
            return $body."\n";
        }

        $yaml = Yaml::dump($frontmatter, 4, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK | Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE);

        return "---\n".$yaml."---\n\n".$body."\n";
    }
}
