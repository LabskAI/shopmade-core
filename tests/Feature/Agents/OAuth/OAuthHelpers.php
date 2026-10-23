<?php

use App\Models\OAuthAuthCode;
use App\Models\OAuthClient;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

const OAUTH_TEST_ROOT = 'https://shop.example';

const OAUTH_TEST_MCP_URL = 'https://shop.example/mcp/agents';

const OAUTH_TEST_CALLBACK = 'https://claude.ai/api/mcp/auth_callback';

/*
| RFC 7636 Appendix B: this verifier hashes (S256) to this challenge.
*/
const OAUTH_TEST_VERIFIER = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk';

const OAUTH_TEST_CHALLENGE = 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM';

/**
 * Serves the app from a fixed origin so issuer, endpoint and resource URLs are known values.
 */
function oauthUseTestHost(): void
{
    config(['app.url' => OAUTH_TEST_ROOT]);
    URL::forceRootUrl(OAUTH_TEST_ROOT);
    URL::forceScheme('https');
}

/**
 * Authorization request parameters, sorted the way Request::fullUrl() sorts them.
 *
 * @param  array<string, string|null>  $overrides  null removes a parameter
 * @return array<string, string>
 */
function oauthAuthorizeQuery(OAuthClient $client, array $overrides = []): array
{
    $query = array_filter([
        'client_id' => $client->id,
        'code_challenge' => OAUTH_TEST_CHALLENGE,
        'code_challenge_method' => 'S256',
        'redirect_uri' => OAUTH_TEST_CALLBACK,
        'response_type' => 'code',
        'state' => 'state-123',
        ...$overrides,
    ], fn (?string $value): bool => $value !== null);

    ksort($query);

    return $query;
}

/**
 * An unconsumed authorization code for the claude.ai callback whose PKCE challenge is OAUTH_TEST_CHALLENGE.
 *
 * @param  array<string, mixed>  $attributes
 * @return array{0: OAuthAuthCode, 1: string}
 */
function oauthCreateCode(array $attributes = []): array
{
    $plaintext = Str::random(43);

    $code = OAuthAuthCode::factory()->create([
        'id' => hash('sha256', $plaintext),
        'code_challenge' => OAUTH_TEST_CHALLENGE,
        ...$attributes,
    ]);

    return [$code, $plaintext];
}

/**
 * The token request that redeems a code created by oauthCreateCode().
 *
 * @param  array<string, string>  $overrides
 * @return array<string, string>
 */
function oauthCodeExchange(OAuthAuthCode $code, string $plaintext, array $overrides = []): array
{
    return [
        'grant_type' => 'authorization_code',
        'client_id' => $code->client_id,
        'code' => $plaintext,
        'redirect_uri' => OAUTH_TEST_CALLBACK,
        'code_verifier' => OAUTH_TEST_VERIFIER,
        ...$overrides,
    ];
}

/**
 * @return array<string, string>
 */
function oauthRedirectQuery(?string $location): array
{
    parse_str((string) parse_url((string) $location, PHP_URL_QUERY), $query);

    return $query;
}
