<?php

namespace App\Core\Front;

/**
 * The company's component stylesheet (theme/components.css): brand styles for the kit's component classes
 * (`front-<name>`, `front-<name>--<variant>`, `front-<name>__<part>`) and for the `class=` tokens pages pass.
 *
 * A save is refused when the CSS could break out of its <style> element or load anything from outside the front:
 * `<`, backslash escapes, `@import`, `@charset`, `@namespace`, `expression(`, `image(`, `image-set(`, `javascript:`,
 * `behavior:`, `-moz-binding` (in any case, with or without whitespace before `(` or `:`, also when split by a comment),
 * or a `url()` that is not a file under assets/. When inlined, the same rules are applied again (anything not allowed
 * is dropped, repeatedly, until nothing is left to drop) so a file committed by hand cannot do more than one saved here.
 */
class FrontComponentsCss
{
    public const MAX_BYTES = 200_000;

    private const FORBIDDEN_CHARACTERS = [
        '<' => 'the character <',
        '\\' => 'backslash escapes',
    ];

    /**
     * At-rules that load or reinterpret the sheet; inline() drops the whole rule up to its semicolon.
     */
    private const AT_RULE = '/@(?:import|charset|namespace)\b[^;]*;?/i';

    /**
     * Functions and properties that run script or load from outside the front. problem() and inline() use the same
     * pattern, so whatever inline() would strip is refused on save.
     */
    private const FORBIDDEN = '/@(?:import|charset|namespace)\b|expression\s*\(|(?:-webkit-)?image-set\s*\(|\bimage\s*\(|javascript\s*:|behavior\s*:|-moz-binding/i';

    private const URL = '/url\(\s*(["\']?)(.*?)\1\s*\)/is';

    /**
     * Why the CSS cannot be saved, or null when it can.
     */
    public function problem(string $css): ?string
    {
        if (strlen($css) > self::MAX_BYTES) {
            return 'components.css is larger than '.(self::MAX_BYTES / 1000).' KB.';
        }

        if (! mb_check_encoding($css, 'UTF-8') || str_contains($css, "\0")) {
            return 'components.css must be UTF-8 text.';
        }

        foreach (self::FORBIDDEN_CHARACTERS as $needle => $label) {
            if (str_contains($css, $needle)) {
                return "components.css may not contain {$label}.";
            }
        }

        $withoutComments = (string) preg_replace('#/\*.*?(?:\*/|$)#s', '', $css);

        foreach ([$css, $withoutComments] as $candidate) {
            if (preg_match(self::FORBIDDEN, $candidate, $match) === 1) {
                $token = strtolower((string) preg_replace('/\s+/', '', $match[0]));

                return "components.css may not contain {$token}".(str_contains($token, 'image') ? ' (use url("assets/…"))' : '').'.';
            }
        }

        if (preg_match_all(self::URL, $css, $matches) !== false) {
            foreach ($matches[2] as $target) {
                if ($this->assetReference(trim($target)) === null) {
                    return 'url() may only point at a file under assets/ (e.g. url("assets/pattern.svg")); found url('.trim($target).').';
                }
            }
        }

        if (substr_count($css, '{') !== substr_count($css, '}')) {
            return 'components.css has unbalanced braces.';
        }

        return null;
    }

    /**
     * The CSS ready to inline in a <style> element: asset url()s rewritten to their URLs, every other url() and any
     * forbidden construct removed.
     *
     * @param  callable(string): ?string  $assetUrl
     */
    public function inline(string $css, callable $assetUrl): string
    {
        if (strlen($css) > self::MAX_BYTES || ! mb_check_encoding($css, 'UTF-8')) {
            return '';
        }

        // Removing one token can join its neighbours into another (`@imp@charset;ort`, `javajavascript:script:`), so
        // the removal repeats until the text no longer changes.
        do {
            $before = $css;
            $css = str_replace(["\0", '<', '\\'], '', $css);
            $css = (string) preg_replace(self::AT_RULE, '', $css);
            $css = (string) preg_replace(self::FORBIDDEN, '', $css);
        } while ($css !== $before);

        return (string) preg_replace_callback(self::URL, function (array $match) use ($assetUrl): string {
            $reference = $this->assetReference(trim($match[2]));
            $url = $reference !== null ? $assetUrl($reference) : null;

            return $url !== null ? 'url("'.str_replace(['"', "\n", "\r"], '', $url).'")' : 'none';
        }, $css);
    }

    /**
     * "assets/x.svg", "./assets/x.svg" or "asset:x.svg" as an asset reference, or null for anything else.
     */
    private function assetReference(string $target): ?string
    {
        $target = (string) preg_replace('#^\./#', '', $target);

        return str_starts_with($target, 'assets/') || str_starts_with($target, 'asset:') ? $target : null;
    }
}
