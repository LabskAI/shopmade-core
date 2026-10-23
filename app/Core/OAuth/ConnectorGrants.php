<?php

namespace App\Core\OAuth;

use App\Core\Agents\Actor;
use App\Core\Agents\AgentRefusedException;
use App\Models\ConnectorGrant;
use App\Models\OAuthAccessToken;
use App\Models\OAuthClient;
use App\Models\OAuthRefreshToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * One grant per user and OAuth client: the consented scopes, last use and revocation. Tokens are bound
 * to a grant; revoking or narrowing the grant takes effect on its live tokens immediately.
 */
class ConnectorGrants
{
    /**
     * @return Collection<int, ConnectorGrant>
     */
    public function active(): Collection
    {
        return ConnectorGrant::query()
            ->with(['user', 'client'])
            ->whereNull('revoked_at')
            ->latest('id')
            ->get();
    }

    /**
     * Revokes the grant and all of its tokens.
     */
    public function revoke(ConnectorGrant $grant, Actor $actor): void
    {
        DB::transaction(function () use ($grant, $actor): void {
            if ($grant->revoked_at === null) {
                $grant->update(['revoked_at' => now(), 'revoked_by' => $actor->userModel()?->id]);
            }

            $this->revokeTokens($grant);
        });
    }

    /**
     * Revokes every access and refresh token of the grant; the grant itself stays active.
     */
    public function revokeTokens(ConnectorGrant $grant): void
    {
        $now = now();

        $grant->accessTokens()->whereNull('revoked_at')->update(['revoked_at' => $now]);
        $grant->refreshTokens()->whereNull('revoked_at')->update(['revoked_at' => $now]);
    }

    /**
     * Validates eligibility and narrows live tokens immediately.
     *
     * Scopes can only be removed here: adding one needs the user's own consent,
     * so the user reconnects and approves it on the consent screen. Live tokens are narrowed to the
     * new set; a token left without scopes is revoked.
     *
     * @param  list<string>  $scopes
     *
     * @throws AgentRefusedException
     */
    public function changeScopes(ConnectorGrant $grant, array $scopes, Actor $actor): ConnectorGrant
    {
        $manager = $actor->userModel();

        if ($actor->isConnector() || ($manager !== null && ! $manager->can('connectors.manage'))) {
            throw new AgentRefusedException('Only an admin can change the scopes of a connector.', 'forbidden');
        }

        if (! $grant->isActive()) {
            throw new AgentRefusedException('This connector was revoked.', 'invalid_state', 'The user can connect again from Claude.');
        }

        $unknown = array_diff(array_map(fn (mixed $scope): string => is_string($scope) ? $scope : get_debug_type($scope), $scopes), Scopes::all());

        if ($unknown !== []) {
            throw new AgentRefusedException('Unknown scope: '.implode(', ', $unknown).'.', 'invalid_params', 'Scopes are '.implode(', ', Scopes::all()).'.');
        }

        $scopes = Scopes::normalize($scopes);

        if ($scopes === []) {
            throw new AgentRefusedException('Keep at least one scope. To remove all access, revoke the connector.', 'invalid_params');
        }

        $owner = $grant->user;
        $ineligible = $owner === null ? $scopes : array_values(array_diff($scopes, Scopes::allowedFor($owner)));

        if ($ineligible !== []) {
            throw new AgentRefusedException(($owner?->name ?? 'This user').' cannot hold the '.implode(', ', $ineligible).' scope.', 'forbidden');
        }

        $added = array_values(array_diff($scopes, $grant->scopes ?? []));

        if ($added !== []) {
            throw new AgentRefusedException(
                'Scopes can only be removed here; '.implode(', ', $added).' was not approved for this connector.',
                'invalid_params',
                'To add a scope, the user reconnects the connector and approves it on the consent screen.',
            );
        }

        DB::transaction(function () use ($grant, $scopes): void {
            $grant->update(['scopes' => $scopes]);
            $this->narrowTokens($grant, $scopes);
        });

        return $grant->refresh();
    }

    /**
     * The active grant for this user and client, created on first consent. A later consent replaces its
     * scopes, and live tokens that held more are narrowed to them.
     *
     * @param  list<string>  $scopes
     */
    public function establish(User $user, OAuthClient $client, array $scopes): ConnectorGrant
    {
        $grant = ConnectorGrant::query()
            ->where('user_id', $user->id)
            ->where('client_id', $client->id)
            ->whereNull('revoked_at')
            ->latest('id')
            ->first();

        if ($grant === null) {
            return ConnectorGrant::query()->create([
                'user_id' => $user->id,
                'client_id' => $client->id,
                'scopes' => $scopes,
            ]);
        }

        $grant->update(['scopes' => $scopes]);
        $this->narrowTokens($grant, $scopes);

        return $grant;
    }

    /**
     * @param  list<string>  $scopes
     */
    private function narrowTokens(ConnectorGrant $grant, array $scopes): void
    {
        $now = now();

        $accessTokens = $grant->accessTokens()->whereNull('revoked_at')->where('expires_at', '>', $now)->get();
        $refreshTokens = $grant->refreshTokens()->whereNull('revoked_at')->whereNull('used_at')->where('expires_at', '>', $now)->get();

        foreach ([...$accessTokens, ...$refreshTokens] as $token) {
            $this->narrowToken($token, $scopes);
        }
    }

    /**
     * @param  list<string>  $scopes
     */
    private function narrowToken(OAuthAccessToken|OAuthRefreshToken $token, array $scopes): void
    {
        $narrowed = array_values(array_intersect($token->scopes ?? [], $scopes));

        if ($narrowed === $token->scopes) {
            return;
        }

        $token->update($narrowed === [] ? ['scopes' => [], 'revoked_at' => now()] : ['scopes' => $narrowed]);
    }
}
