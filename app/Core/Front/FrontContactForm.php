<?php

namespace App\Core\Front;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Spam checks for the built-in contact form. Front pages are stateless (no session, no CSRF token), so each
 * rendered form carries an encrypted timestamp instead: a post must come at least a few seconds after the page
 * was rendered and within a day of it, and must leave the hidden honeypot field empty.
 */
class FrontContactForm
{
    public const string HONEYPOT = 'website';

    public const int MIN_SECONDS = 3;

    public const int MAX_SECONDS = 86400;

    public static function token(): string
    {
        return Crypt::encryptString((string) now()->getTimestamp());
    }

    public static function tokenIsFresh(mixed $token): bool
    {
        if (! is_string($token) || $token === '') {
            return false;
        }

        try {
            $issuedAt = (int) Crypt::decryptString($token);
        } catch (DecryptException) {
            return false;
        }

        $age = now()->getTimestamp() - $issuedAt;

        return $age >= self::MIN_SECONDS && $age <= self::MAX_SECONDS;
    }
}
