<?php

namespace App\Core\Front;

/**
 * sscss for the front kit: the CSS a rendered page needs, generated from the classes it uses, the way
 * `sscss page.html --write` does (https://sscss.herton.work). The rules come from resources/front/sscss.json,
 * built from the @labs_kai/sscss package by `npm run front`; nothing is hand-copied.
 *
 * Kit views, company components and page `class="…"` tokens can all use the vocabulary (`f ac jb`, `gr g5 m-c2`,
 * `py8`, `an-rise`…); a page carries only the rules it uses, in one inline `<style data-sscss>`.
 */
class FrontCss
{
    /** The placeholder the layout prints where the page's utilities go. */
    public const PLACEHOLDER = '<style data-sscss></style>';

    /**
     * @var array{version: string, core: string, globals: list<string>, rules: array<string, array{0: string, 1: ?string, 2: list<int>}>}|null
     */
    private static ?array $vocabulary = null;

    private static ?string $kit = null;

    /**
     * Reset and default tokens. The kit maps the company's --color-* tokens onto sscss's roles right after it.
     */
    public static function core(): string
    {
        return self::vocabulary()['core'];
    }

    /**
     * sscss roles (--ink --mut --bg --sf --sf2 --acc --acci --r) read the company's theme tokens, so every utility
     * follows theme/tokens.css. A company that defines its tokens again under prefers-color-scheme: dark gets dark mode.
     */
    public static function roles(): string
    {
        return ':root{--ink:var(--color-ink,#1b1b1b);--mut:var(--color-muted,#4b5563);--bg:var(--color-bg,#fff);'
            .'--sf:var(--color-surface,#f6f6f6);--sf2:var(--color-surface-2,var(--color-line,#dfe3e9));'
            .'--acc:var(--color-accent,#0b57a4);--acci:var(--color-accent-ink,#fff);--r:var(--radius,0)}';
    }

    /**
     * The kit's own stylesheet (resources/front/kit.css), minified: comments and whitespace removed.
     */
    public static function kit(): string
    {
        return self::$kit ??= self::minify((string) file_get_contents(resource_path('front/kit.css')));
    }

    /**
     * A small, safe CSS minifier for the kit's own files: drops comments, collapses whitespace and trims it around
     * punctuation. Strings in the kit CSS never contain these characters.
     */
    public static function minify(string $css): string
    {
        $css = (string) preg_replace('#/\*.*?\*/#s', '', $css);
        $css = (string) preg_replace('/\s+/', ' ', $css);
        $css = (string) preg_replace('/\s*([{};,>])\s*/', '$1', $css);
        $css = (string) preg_replace('/:\s+/', ':', $css);

        return trim(str_replace(';}', '}', $css));
    }

    /**
     * The utility rules for the classes used in $html: plain rules sorted, then the ≥768px and ≥1024px groups,
     * then motion globals (keyframes, the reduced-motion switch) once.
     */
    public static function utilities(string $html): string
    {
        $vocabulary = self::vocabulary();
        $base = [];
        $media = ['m' => [], 'l' => []];
        $globals = [];
        $classes = self::classesIn($html);
        sort($classes);

        foreach ($classes as $class) {
            $rule = $vocabulary['rules'][$class] ?? null;

            if ($rule === null) {
                continue;
            }

            [$css, $variant, $globalIds] = $rule;

            if ($variant !== null) {
                $media[$variant][] = $css;
            } else {
                $base[] = $css;
            }

            foreach ($globalIds as $id) {
                $globals[$id] = $vocabulary['globals'][$id];
            }
        }

        ksort($globals);
        $css = implode('', $base);

        if ($media['m'] !== []) {
            $css .= '@media(min-width:768px){'.implode('', $media['m']).'}';
        }

        if ($media['l'] !== []) {
            $css .= '@media(min-width:1024px){'.implode('', $media['l']).'}';
        }

        return $css.implode('', $globals);
    }

    /**
     * Fill the layout's placeholder with the page's utilities.
     */
    public static function inline(string $html): string
    {
        $position = strpos($html, self::PLACEHOLDER);

        if ($position === false) {
            return $html;
        }

        $style = '<style data-sscss>'.self::utilities($html).'</style>';

        return substr_replace($html, $style, $position, strlen(self::PLACEHOLDER));
    }

    /**
     * Class names used in tags (outside <style> and <script>), plus the ones Minim toggles with data-class.
     *
     * @return list<string>
     */
    public static function classesIn(string $html): array
    {
        $markup = (string) preg_replace(['#<style\b[^>]*>.*?</style>#is', '#<script\b[^>]*>.*?</script>#is'], '', $html);
        $used = [];

        preg_match_all('/(?<![\w-])class="([^"]*)"/', $markup, $classMatches);

        foreach ($classMatches[1] as $list) {
            foreach (preg_split('/\s+/', $list, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $class) {
                $used[$class] = true;
            }
        }

        preg_match_all('/\bdata-class="([^"]*)"/', $markup, $toggleMatches);

        foreach ($toggleMatches[1] as $list) {
            foreach (preg_split('/\s+/', $list, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $pair) {
                $class = explode(':', $pair)[0];

                if ($class !== '') {
                    $used[$class] = true;
                }
            }
        }

        return array_keys($used);
    }

    /**
     * @return array{version: string, core: string, globals: list<string>, rules: array<string, array{0: string, 1: ?string, 2: list<int>}>}
     */
    private static function vocabulary(): array
    {
        return self::$vocabulary ??= json_decode((string) file_get_contents(resource_path('front/sscss.json')), true, flags: JSON_THROW_ON_ERROR);
    }
}
