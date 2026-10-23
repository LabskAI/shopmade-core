<?php

namespace App\Core\Front;

/**
 * The CSS custom properties of a front's theme/tokens.css: read them, check a new value, and write new
 * values into declarations that already exist. Comments, formatting and every other line stay as written.
 */
class FrontThemeTokens
{
    /**
     * Substrings a value may never contain: they end the declaration, open a block, a tag or a comment,
     * escape characters, or load something from elsewhere.
     *
     * @var list<string>
     */
    public const FORBIDDEN = [';', '{', '}', '<', '>', '\\', 'url(', 'expression(', '@import', '/*', '*/'];

    public const MAX_VALUE_LENGTH = 200;

    private const DECLARATION = '/(--[A-Za-z0-9_-]+)(\s*:\s*)([^;{}]*?)(\s*;)/';

    private const COLOR = '/^(?:#[0-9a-f]{3,8}|(?:rgba?|hsla?|oklch|oklab|lab|lch)\([0-9a-z.,%\s\/+-]+\))$/i';

    /**
     * Each custom property with its first declared value, in file order. Declarations inside comments are skipped.
     *
     * @return array<string, string>
     */
    public function parse(string $css): array
    {
        $tokens = [];

        foreach ($this->segments($css) as [$isComment, $text]) {
            if ($isComment) {
                continue;
            }

            preg_match_all(self::DECLARATION, $text, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $tokens[$match[1]] ??= trim($match[3]);
            }
        }

        return $tokens;
    }

    /**
     * Why a value cannot be written, or null when it is safe.
     */
    public function problem(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return 'A value is required.';
        }

        if (mb_strlen($value) > self::MAX_VALUE_LENGTH) {
            return 'Keep a value under '.self::MAX_VALUE_LENGTH.' characters.';
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
            return 'A value must be a single line.';
        }

        foreach (self::FORBIDDEN as $needle) {
            if (str_contains(strtolower($value), $needle)) {
                return sprintf('"%s" is not allowed in a theme value.', $needle);
            }
        }

        return null;
    }

    /**
     * Writes the new values into the first declaration of each property; properties the file does not declare are ignored.
     *
     * @param  array<string, string>  $values
     */
    public function apply(string $css, array $values): string
    {
        $remaining = array_map(fn (string $value): string => trim($value), $values);
        $result = '';

        foreach ($this->segments($css) as [$isComment, $text]) {
            if (! $isComment) {
                $text = (string) preg_replace_callback(self::DECLARATION, function (array $match) use (&$remaining): string {
                    if (! array_key_exists($match[1], $remaining)) {
                        return $match[0];
                    }

                    $value = $remaining[$match[1]];
                    unset($remaining[$match[1]]);

                    return $match[1].$match[2].$value.$match[4];
                }, $text);
            }

            $result .= $text;
        }

        return $result;
    }

    /**
     * The editor section a token belongs to, from its name.
     */
    public function group(string $name): string
    {
        return match (true) {
            str_starts_with($name, '--color') => 'Colors',
            str_starts_with($name, '--font') => 'Fonts',
            str_starts_with($name, '--text'), str_starts_with($name, '--leading') => 'Type scale',
            str_starts_with($name, '--space') => 'Spacing',
            default => 'Layout',
        };
    }

    public function isColor(string $value): bool
    {
        return preg_match(self::COLOR, trim($value)) === 1;
    }

    /**
     * The CSS split into code and comments, in order: [isComment, text].
     *
     * @return list<array{0: bool, 1: string}>
     */
    private function segments(string $css): array
    {
        $parts = preg_split('#(/\*.*?\*/)#s', $css, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($parts === false) {
            return [[false, $css]];
        }

        $segments = [];

        foreach ($parts as $index => $part) {
            $segments[] = [$index % 2 === 1, $part];
        }

        return $segments;
    }
}
