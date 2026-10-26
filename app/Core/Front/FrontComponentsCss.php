<?php

namespace App\Core\Front;

/**
 * The company's component stylesheet (theme/components.css): brand styles for the kit's component classes
 * (`front-<name>`, `front-<name>--<variant>`, `front-<name>__<part>`) and for the `class=` tokens pages pass.
 *
 * A save is refused when the CSS could break out of its <style> element or load anything from outside the front:
 * `<`, backslash escapes, `@import`, `@charset`, `expression(`, `image-set(`, `javascript:`, `behavior:`, `-moz-binding`, or a
 * `url()` that is not a file under assets/. When inlined, the same rules are applied again (anything not allowed is
 * dropped) so a file committed by hand cannot do more than one saved here.
 */
class FrontComponentsCss
{
    public const MAX_BYTES = 200_000;

    private const FORBIDDEN = [
        '<' => 'the character <',
        '\\' => 'backslash escapes',
        '@import' => '@import',
        '@charset' => '@charset',
        '@namespace' => '@namespace',
        'expression(' => 'expression(',
        'image-set(' => 'image-set( (use url("assets/…"))',
        'javascript:' => 'javascript:',
        'behavior:' => 'behavior:',
        '-moz-binding' => '-moz-binding',
    ];

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

        $lower = strtolower($css);

        foreach (self::FORBIDDEN as $needle => $label) {
            if (str_contains($lower, $needle)) {
                return "components.css may not contain {$label}.";
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

        $css = str_replace(["\0", '<', '\\'], '', $css);
        $css = (string) preg_replace('/@(?:import|charset|namespace)\b[^;]*;?/i', '', $css);
        $css = (string) preg_replace('/expression\s*\(|(?:-webkit-)?image-set\s*\(|javascript\s*:|behavior\s*:|-moz-binding/i', '', $css);

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
