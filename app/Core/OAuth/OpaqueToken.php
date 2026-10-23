<?php

namespace App\Core\OAuth;

/**
 * Opaque secrets (authorization codes, access and refresh tokens): 32 random bytes, base64url-encoded.
 * Only the SHA-256 of a secret is ever stored.
 */
final class OpaqueToken
{
    public static function generate(): string
    {
        return self::base64Url(random_bytes(32));
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
