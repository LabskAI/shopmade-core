<?php

use App\Core\OAuth\RedirectUriPolicy;

return [

    /*
    | One install serves one company. Its public front lives in
    | {repo}/fronts/{company}; the website editor and MCP write there directly.
    */

    'company' => env('AGENTS_COMPANY', 'demo'),

    'paths' => [
        'repo' => env('AGENTS_REPO_DIR', base_path()),
        'fronts' => 'fronts',
    ],

    'front' => [
        'prefix' => env('AGENTS_FRONT_PREFIX', 'site'),
        'host' => env('AGENTS_FRONT_HOST'),
    ],

    'mcp' => [
        'path' => 'mcp/agents',
        'rate_limit_per_minute' => (int) env('AGENTS_MCP_RATE_LIMIT', 60),
        'max_body_bytes' => 262144,
        'max_batch' => (int) env('AGENTS_MCP_MAX_BATCH', 10),
        'session_ttl_minutes' => 1440,
        'protocol_versions' => ['2025-11-25', '2025-06-18', '2025-03-26'],
        'server_name' => 'shopmade-agents',
    ],

    'oauth' => [
        'access_token_ttl_minutes' => 60,
        'refresh_token_ttl_days' => 30,
        'auth_code_ttl_minutes' => 10,
        /*
        | Exact redirect URIs a public client may register. Claude and Cursor
        | are built in. AGENTS_OAUTH_EXTRA_REDIRECT_URIS appends more (comma-
        | separated, trimmed, empties dropped). Matching is exact; no wildcards.
        | A registration that lists any disallowed URI is rejected in full.
        */
        'redirect_uris' => [
            'https://claude.ai/api/mcp/auth_callback',
            'https://claude.com/api/mcp/auth_callback',
            'https://www.cursor.com/agents/mcp/oauth/callback',
        ],
        'extra_redirect_uris' => RedirectUriPolicy::extraRedirectUris(
            (string) env('AGENTS_OAUTH_EXTRA_REDIRECT_URIS', ''),
        ),
        'allow_loopback_redirects' => (bool) env('AGENTS_OAUTH_ALLOW_LOOPBACK', in_array(env('APP_ENV', 'production'), ['local', 'testing'], true)),
    ],

];
