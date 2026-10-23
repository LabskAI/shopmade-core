<?php

use App\Models\ConnectorGrant;
use App\Models\OAuthAccessToken;
use App\Models\OAuthRefreshToken;
use Database\Factories\OAuthAccessTokenFactory;

require_once __DIR__.'/OAuthHelpers.php';

beforeEach(function () {
    oauthUseTestHost();
});

it('revokes an access token on its own', function () {
    [$token, $plaintext] = OAuthAccessTokenFactory::createWithPlaintext();
    $sibling = OAuthAccessToken::factory()->for($token->grant, 'grant')->create();

    $this->post('/oauth/revoke', ['token' => $plaintext, 'client_id' => $token->client_id])
        ->assertOk()
        ->assertContent('');

    expect($token->fresh()->revoked_at)->not->toBeNull()
        ->and($sibling->fresh()->revoked_at)->toBeNull()
        ->and($token->grant->fresh()->revoked_at)->toBeNull();
});

it('ends the connection when its refresh token is revoked', function () {
    $grant = ConnectorGrant::factory()->create();
    $access = OAuthAccessToken::factory()->for($grant, 'grant')->create();
    OAuthRefreshToken::factory()->for($grant, 'grant')->withPlaintext('refresh-plaintext')->create();

    $this->post('/oauth/revoke', ['token' => 'refresh-plaintext', 'token_type_hint' => 'refresh_token'])->assertOk();

    expect($grant->fresh()->revoked_at)->not->toBeNull()
        ->and($access->fresh()->revoked_at)->not->toBeNull()
        ->and(OAuthRefreshToken::query()->sole()->revoked_at)->not->toBeNull();
});

it('answers 200 without revoking anything for unknown tokens or another client', function (Closure $request) {
    [$token, $plaintext] = OAuthAccessTokenFactory::createWithPlaintext();

    $this->post('/oauth/revoke', $request($plaintext))->assertOk();

    expect($token->fresh()->revoked_at)->toBeNull();
})->with([
    'unknown token' => [fn (string $plaintext): array => ['token' => $plaintext.'-unknown']],
    'token of another client' => [fn (string $plaintext): array => ['token' => $plaintext, 'client_id' => 'smc_someone_else']],
]);

it('requires the token parameter', function () {
    $this->post('/oauth/revoke', [])
        ->assertBadRequest()
        ->assertJsonPath('error', 'invalid_request');
});
