<?php

namespace App\Core\OAuth;

/**
 * Which redirect URIs a client may register and use: an exact entry of `agents.oauth.redirect_uris`
 * (claude.ai, claude.com, cursor.com) or `agents.oauth.extra_redirect_uris`, or — while
 * `agents.oauth.allow_loopback_redirects` is on — an http://localhost:<port>/… or
 * http://127.0.0.1:<port>/… callback (MCP Inspector, Claude Code). Matching is exact; no wildcards.
 */
class RedirectUriPolicy
{
    private const array LOOPBACK_HOSTS = ['localhost', '127.0.0.1'];

    public function allows(string $uri): bool
    {
        if ($uri === '' || strlen($uri) > 2000 || str_contains($uri, '#')) {
            return false;
        }

        if (in_array($uri, $this->allowlist(), true)) {
            return true;
        }

        return $this->loopbackAllowed() && $this->isLoopback($uri);
    }

    /**
     * http (never https) on localhost or 127.0.0.1 with an explicit port; no user info, fragment or
     * odd characters, and the URI must literally start with that origin so parser quirks cannot widen it.
     */
    public function isLoopback(string $uri): bool
    {
        if (preg_match('/[\x00-\x20\x7F\\\\]/', $uri) === 1) {
            return false;
        }

        $parts = parse_url($uri);

        if (! is_array($parts) || ($parts['scheme'] ?? null) !== 'http' || ! in_array($parts['host'] ?? null, self::LOOPBACK_HOSTS, true)) {
            return false;
        }

        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
            return false;
        }

        $port = $parts['port'] ?? null;

        if ($port === null || $port < 1 || $port > 65535) {
            return false;
        }

        $origin = 'http://'.$parts['host'].':'.$port;
        $rest = substr($uri, strlen($origin));

        return str_starts_with($uri, $origin) && ($rest === '' || $rest[0] === '/' || $rest[0] === '?');
    }

    public function describe(): string
    {
        $allowed = implode(', ', $this->allowlist());

        return $this->loopbackAllowed()
            ? "Allowed: {$allowed}, or http://localhost:<port>/… and http://127.0.0.1:<port>/… callbacks."
            : "Allowed: {$allowed}.";
    }

    /**
     * Comma-separated exact redirect URIs from `AGENTS_OAUTH_EXTRA_REDIRECT_URIS`.
     * Trims each value and drops empties. Matching stays exact — no wildcards.
     *
     * @return list<string>
     */
    public static function extraRedirectUris(string $csv): array
    {
        return array_values(array_unique(array_filter(array_map(trim(...), explode(',', $csv)))));
    }

    /**
     * @return list<string>
     */
    private function allowlist(): array
    {
        return array_values(array_unique(array_filter([
            ...(array) config('agents.oauth.redirect_uris', []),
            ...(array) config('agents.oauth.extra_redirect_uris', []),
        ], is_string(...))));
    }

    private function loopbackAllowed(): bool
    {
        return (bool) config('agents.oauth.allow_loopback_redirects', false);
    }
}
