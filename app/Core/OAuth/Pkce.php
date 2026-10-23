<?php

namespace App\Core\OAuth;

/**
 * PKCE (RFC 7636) with the S256 method only.
 */
final class Pkce
{
    /**
     * 43 to 128 unreserved characters, the format of both a code verifier and a code challenge.
     */
    private const string FORMAT = '/^[A-Za-z0-9\-._~]{43,128}$/D';

    public static function isWellFormed(string $value): bool
    {
        return preg_match(self::FORMAT, $value) === 1;
    }

    public static function challenge(string $verifier): string
    {
        return OpaqueToken::base64Url(hash('sha256', $verifier, true));
    }

    public static function verify(string $verifier, string $challenge): bool
    {
        return self::isWellFormed($verifier) && hash_equals($challenge, self::challenge($verifier));
    }
}
