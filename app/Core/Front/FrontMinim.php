<?php

namespace App\Core\Front;

/**
 * Minim (https://minim.herton.work) for the front kit: the one way a public page gets interactive. A page whose
 * markup carries a Minim state tag (`<script type="application/json" data-state="…">`) gets Minim lite inlined at the
 * end of <body>, under the page's CSP nonce; a page without one ships no script at all.
 */
class FrontMinim
{
    public static function inline(string $html, ?string $nonce): string
    {
        $position = strripos($html, '</body>');

        if ($position === false || preg_match('/<script\b[^>]*\bdata-state\b/i', $html) !== 1) {
            return $html;
        }

        $script = '<script'.($nonce !== null ? ' nonce="'.e($nonce).'"' : '').'>'
            .(string) file_get_contents(resource_path('front/minim-lite.min.js')).'</script>';

        return substr_replace($html, $script, $position, 0);
    }
}
