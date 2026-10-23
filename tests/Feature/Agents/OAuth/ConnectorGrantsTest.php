<?php

use App\Core\Agents\Actor;
use App\Core\Agents\AgentRefusedException;
use App\Core\OAuth\ConnectorGrants;
use App\Enums\UserRole;
use App\Models\ConnectorGrant;
use App\Models\OAuthAccessToken;
use App\Models\OAuthRefreshToken;
use App\Models\User;

require_once __DIR__.'/OAuthHelpers.php';

beforeEach(function () {
    oauthUseTestHost();
});

it('lists the active grants with their user and client', function () {
    $active = ConnectorGrant::factory()->create();
    ConnectorGrant::factory()->create(['revoked_at' => now()]);

    $grants = app(ConnectorGrants::class)->active();

    expect($grants)->toHaveCount(1)
        ->and($grants->first()->is($active))->toBeTrue()
        ->and($grants->first()->relationLoaded('user'))->toBeTrue()
        ->and($grants->first()->relationLoaded('client'))->toBeTrue();
});

it('revokes a grant and every token it issued, and nothing else', function () {
    $admin = User::factory()->superAdmin()->create();
    $grant = ConnectorGrant::factory()->create();
    $access = OAuthAccessToken::factory()->for($grant, 'grant')->create();
    $refresh = OAuthRefreshToken::factory()->for($grant, 'grant')->create();
    $otherToken = OAuthAccessToken::factory()->create();

    app(ConnectorGrants::class)->revoke($grant, Actor::forUser($admin));

    expect($grant->fresh())
        ->revoked_at->not->toBeNull()
        ->revoked_by->toBe($admin->id)
        ->and($access->fresh()->revoked_at)->not->toBeNull()
        ->and($refresh->fresh()->revoked_at)->not->toBeNull()
        ->and($otherToken->fresh()->revoked_at)->toBeNull();
});

it('narrows live tokens immediately when scopes are removed', function () {
    $grant = ConnectorGrant::factory()->create(['scopes' => ['director', 'operator']]);
    $access = OAuthAccessToken::factory()->for($grant, 'grant')->create(['scopes' => ['director', 'operator']]);
    $refresh = OAuthRefreshToken::factory()->for($grant, 'grant')->create(['scopes' => ['director', 'operator']]);
    $operatorOnly = OAuthAccessToken::factory()->for($grant, 'grant')->create(['scopes' => ['operator']]);

    $changed = app(ConnectorGrants::class)->changeScopes($grant, ['director'], Actor::forUser(User::factory()->superAdmin()->create()));

    expect($changed->scopes)->toBe(['director'])
        ->and($access->fresh()->scopes)->toBe(['director'])
        ->and($refresh->fresh()->scopes)->toBe(['director'])
        ->and($operatorOnly->fresh()->revoked_at)->not->toBeNull();
});

it('refuses scope changes the grant cannot take', function (Closure $arrange, string $reason) {
    $grant = ConnectorGrant::factory()->create(['scopes' => ['operator']]);
    [$scopes, $actor] = $arrange($grant);

    expect(fn () => app(ConnectorGrants::class)->changeScopes($grant, $scopes, $actor))
        ->toThrow(fn (AgentRefusedException $refusal) => expect($refusal->reason)->toBe($reason));

    expect($grant->fresh()->scopes)->toBe(['operator']);
})->with([
    'a scope the user cannot hold' => [function (ConnectorGrant $grant): array {
        $grant->user->update(['role' => UserRole::User]);

        return [['operator'], Actor::cli()];
    }, 'forbidden'],
    'adding a scope' => [fn (ConnectorGrant $grant): array => [['director', 'operator'], Actor::cli()], 'invalid_params'],
    'an unknown scope' => [fn (ConnectorGrant $grant): array => [['root'], Actor::cli()], 'invalid_params'],
    'no scopes' => [fn (ConnectorGrant $grant): array => [[], Actor::cli()], 'invalid_params'],
    'a revoked grant' => [function (ConnectorGrant $grant): array {
        $grant->update(['revoked_at' => now()]);

        return [['operator'], Actor::cli()];
    }, 'invalid_state'],
    'the connector itself' => [fn (ConnectorGrant $grant): array => [['operator'], Actor::connector($grant->user, $grant->client_id)], 'forbidden'],
    'a staff user' => [fn (ConnectorGrant $grant): array => [['operator'], Actor::forUser(User::factory()->user()->create())], 'forbidden'],
]);
