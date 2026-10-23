<?php

use App\Enums\ConnectorScope;
use App\Models\ConnectorGrant;
use App\Models\McpSession;
use App\Models\OAuthAccessToken;
use App\Models\OAuthAuthCode;
use App\Models\OAuthClient;
use App\Models\OAuthRefreshToken;
use Database\Factories\OAuthAccessTokenFactory;
use Illuminate\Database\Eloquent\Model;

it('persists a row from every connector factory', function (Model $model) {
    $this->assertModelExists($model);
})->with([
    'oauth client' => fn () => OAuthClient::factory()->create(),
    'oauth auth code' => fn () => OAuthAuthCode::factory()->create(),
    'connector grant' => fn () => ConnectorGrant::factory()->create(),
    'access token' => fn () => OAuthAccessToken::factory()->create(),
    'refresh token' => fn () => OAuthRefreshToken::factory()->create(),
    'mcp session' => fn () => McpSession::factory()->create(),
]);

it('creates an access token whose stored hash matches the returned plaintext', function () {
    [$token, $plaintext] = OAuthAccessTokenFactory::createWithPlaintext();

    expect($token->token_hash)->toBe(hash('sha256', $plaintext))
        ->and($token->client_id)->toBe($token->grant->client_id)
        ->and($token->user_id)->toBe($token->grant->user_id);
});

it('reports whether a connector grant is active and holds a scope', function () {
    $grant = ConnectorGrant::factory()->create(['scopes' => ['director']]);

    expect($grant->isActive())->toBeTrue()
        ->and($grant->hasScope(ConnectorScope::Director))->toBeTrue()
        ->and($grant->hasScope('operator'))->toBeFalse();

    $grant->update(['revoked_at' => now()]);

    expect($grant->isActive())->toBeFalse();
});
