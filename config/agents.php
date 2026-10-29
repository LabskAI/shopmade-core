<?php

use App\Core\OAuth\RedirectUriPolicy;

return [

    /*
    | One install serves one company. Its live site content lives in
    | {repo}/content/{company}, a folder kept out of the code (gitignored, a
    | volume in Docker) with its own git history: the website editor and MCP
    | write there and commit every save. front:install copies the starter
    | content from {repo}/fronts/{company} (or fronts/demo) on first boot.
    */

    'company' => env('AGENTS_COMPANY', 'demo'),

    'paths' => [
        'repo' => env('AGENTS_REPO_DIR', base_path()),
        'fronts' => env('AGENTS_CONTENT_DIR', 'content'),
        'starter' => 'fronts',
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
        | Exact redirect URIs a public client may register. Claude, ChatGPT and
        | Cursor are built in. AGENTS_OAUTH_EXTRA_REDIRECT_URIS appends more
        | (comma-separated, trimmed, empties dropped). Matching is exact; no
        | wildcards. A registration that lists any disallowed URI is rejected
        | in full. ChatGPT also uses per-connector callbacks of the form
        | https://chatgpt.com/connector/oauth/{callback_id}, matched by
        | redirect_uri_patterns (anchored, one path segment of [A-Za-z0-9_-]).
        */
        'redirect_uris' => [
            'https://claude.ai/api/mcp/auth_callback',
            'https://claude.com/api/mcp/auth_callback',
            'https://chatgpt.com/connector_platform_oauth_redirect',
            'https://www.cursor.com/agents/mcp/oauth/callback',
        ],
        'redirect_uri_patterns' => [
            '#\Ahttps://chatgpt\.com/connector/oauth/[A-Za-z0-9_-]{1,128}\z#',
        ],
        'extra_redirect_uris' => RedirectUriPolicy::extraRedirectUris(
            (string) env('AGENTS_OAUTH_EXTRA_REDIRECT_URIS', ''),
        ),
        'allow_loopback_redirects' => (bool) env('AGENTS_OAUTH_ALLOW_LOOPBACK', in_array(env('APP_ENV', 'production'), ['local', 'testing'], true)),
    ],

];
