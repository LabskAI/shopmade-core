<?php

use App\Core\OAuth\AccessTokens;
use App\Enums\UserRole;
use App\Models\ConnectorGrant;
use App\Models\OAuthAccessToken;
use Database\Factories\OAuthAccessTokenFactory;

require_once __DIR__.'/OAuthHelpers.php';

beforeEach(function () {
    oauthUseTestHost();
});

it('resolves a live bearer token with its grant and user loaded', function () {
    [$token, $plaintext] = OAuthAccessTokenFactory::createWithPlaintext(['resource' => OAUTH_TEST_MCP_URL]);

    $resolved = app(AccessTokens::class)->resolve($plaintext);

    expect($resolved?->is($token))->toBeTrue()
        ->and($resolved->relationLoaded('grant'))->toBeTrue()
        ->and($resolved->relationLoaded('user'))->toBeTrue();
});

it('refuses a bearer that is not a live token for this endpoint', function (Closure $spoil) {
    [$token, $plaintext] = OAuthAccessTokenFactory::createWithPlaintext();

    $bearer = $spoil($token, $plaintext);

    expect(app(AccessTokens::class)->resolve($bearer))->toBeNull();
})->with([
    'unknown' => [fn (OAuthAccessToken $token, string $plaintext): string => $plaintext.'x'],
    'expired' => [function (OAuthAccessToken $token, string $plaintext): string {
        $token->update(['expires_at' => now()->subSecond()]);

        return $plaintext;
    }],
    'revoked' => [function (OAuthAccessToken $token, string $plaintext): string {
        $token->update(['revoked_at' => now()]);

        return $plaintext;
    }],
    'grant revoked' => [function (OAuthAccessToken $token, string $plaintext): string {
        $token->grant->update(['revoked_at' => now()]);

        return $plaintext;
    }],
    'client revoked' => [function (OAuthAccessToken $token, string $plaintext): string {
        $token->client->update(['revoked_at' => now()]);

        return $plaintext;
    }],
    'issued for another resource' => [function (OAuthAccessToken $token, string $plaintext): string {
        $token->update(['resource' => 'https://elsewhere.example/mcp/agents']);

        return $plaintext;
    }],
]);

it('shrinks the effective scopes when the user is demoted', function (UserRole $role, array $effective) {
    $grant = ConnectorGrant::factory()->create(['scopes' => ['director', 'operator']]);
    [, $plaintext] = OAuthAccessTokenFactory::createWithPlaintext(['grant_id' => $grant->id]);
    $tokens = app(AccessTokens::class);

    $grant->user->update(['role' => $role]);

    expect($tokens->effectiveScopes($tokens->resolve($plaintext)))->toBe($effective);
})->with([
    'still super admin' => [UserRole::SuperAdmin, ['director', 'operator']],
    'admin' => [UserRole::Admin, ['director', 'operator']],
    'staff user' => [UserRole::User, []],
]);

it('limits the effective scopes to what the grant still allows', function () {
    $grant = ConnectorGrant::factory()->create(['scopes' => ['director', 'operator']]);
    [, $plaintext] = OAuthAccessTokenFactory::createWithPlaintext(['grant_id' => $grant->id]);
    $tokens = app(AccessTokens::class);

    $grant->update(['scopes' => ['director']]);

    expect($tokens->effectiveScopes($tokens->resolve($plaintext)))->toBe(['director']);
});

it('records the use of a token and its grant at most once a minute', function () {
    $this->travelTo('2026-10-05 12:00:00');
    $token = OAuthAccessToken::factory()->create();
    $tokens = app(AccessTokens::class);

    $tokens->touch($token);
    $this->travelTo('2026-10-05 12:00:40');
    $tokens->touch($token);

    expect($token->fresh()->last_used_at->toDateTimeString())->toBe('2026-10-05 12:00:00')
        ->and($token->grant->fresh()->last_seen_at->toDateTimeString())->toBe('2026-10-05 12:00:00');
});

it('records the use again once a minute has passed', function () {
    $grant = ConnectorGrant::factory()->create(['last_seen_at' => '2026-10-05 12:00:00']);
    $token = OAuthAccessToken::factory()->for($grant, 'grant')->create(['last_used_at' => '2026-10-05 12:00:00']);
    $this->travelTo('2026-10-05 12:01:05');

    app(AccessTokens::class)->touch($token);

    expect($token->fresh()->last_used_at->toDateTimeString())->toBe('2026-10-05 12:01:05')
        ->and($grant->fresh()->last_seen_at->toDateTimeString())->toBe('2026-10-05 12:01:05');
});
