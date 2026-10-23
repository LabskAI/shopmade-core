<?php

namespace App\Core\OAuth;

class OAuthUrls
{
    /**
     * The authorization server's issuer identifier: the APP_URL origin, no trailing slash.
     */
    public static function issuer(): string
    {
        $parts = parse_url((string) config('app.url'));

        if (! is_array($parts) || ! isset($parts['host'])) {
            return rtrim(url('/'), '/');
        }

        $scheme = strtolower($parts['scheme'] ?? 'https');

        return $scheme.'://'.strtolower($parts['host']).self::port($scheme, $parts['port'] ?? null);
    }

    /**
     * url(config('agents.mcp.path')).
     */
    public static function mcpResource(): string
    {
        return url((string) config('agents.mcp.path'));
    }

    /**
     * The RFC 9728 metadata URL for the MCP endpoint (well-known prefix + the endpoint's path).
     */
    public static function resourceMetadata(): string
    {
        return url('.well-known/oauth-protected-resource/'.trim((string) config('agents.mcp.path'), '/'));
    }

    /**
     * Compares a `resource` value (RFC 8707) with the MCP endpoint: scheme and host are case-insensitive,
     * a default port or a trailing slash does not matter, a fragment never matches.
     */
    public static function isMcpResource(string $uri): bool
    {
        $candidate = self::canonical($uri);

        return $candidate !== null && $candidate === self::canonical(self::mcpResource());
    }

    /**
     * The client's redirect URI with authorization response parameters plus our issuer (RFC 9207).
     *
     * @param  array<string, string|null>  $parameters
     */
    public static function callback(string $redirectUri, array $parameters): string
    {
        $query = http_build_query(
            array_filter([...$parameters, 'iss' => self::issuer()], fn (?string $value): bool => $value !== null),
            '',
            '&',
            PHP_QUERY_RFC3986,
        );

        return $redirectUri.(str_contains($redirectUri, '?') ? '&' : '?').$query;
    }

    private static function canonical(string $uri): ?string
    {
        if (str_contains($uri, '#')) {
            return null;
        }

        $parts = parse_url($uri);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $scheme = strtolower($parts['scheme']);
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';

        return $scheme.'://'.strtolower($parts['host']).self::port($scheme, $parts['port'] ?? null).rtrim($parts['path'] ?? '', '/').$query;
    }

    private static function port(string $scheme, ?int $port): string
    {
        if ($port === null || ($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80)) {
            return '';
        }

        return ':'.$port;
    }
}
