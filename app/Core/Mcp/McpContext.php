<?php

namespace App\Core\Mcp;

use App\Core\Agents\Actor;
use App\Enums\ConnectorScope;
use App\Models\ConnectorGrant;
use App\Models\OAuthAccessToken;
use App\Models\User;
use Illuminate\Http\Request;
use LogicException;

/**
 * Who is calling the MCP server: the connector user, its grant and token, the effective scopes
 * (token scopes narrowed to what the user's role allows) and the audit actor.
 */
final readonly class McpContext
{
    /**
     * @param  list<string>  $scopes
     */
    public function __construct(
        public User $user,
        public ConnectorGrant $grant,
        public OAuthAccessToken $token,
        public string $clientId,
        public array $scopes,
        public Actor $actor,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $context = $request->attributes->get(self::class);

        if (! $context instanceof self) {
            throw new LogicException('The MCP route must run behind the AuthenticateConnector middleware.');
        }

        return $context;
    }

    public function hasScope(ConnectorScope|string $scope): bool
    {
        return in_array($scope instanceof ConnectorScope ? $scope->value : $scope, $this->scopes, true);
    }

    public function scopeList(): string
    {
        return $this->scopes === [] ? 'none' : implode(', ', $this->scopes);
    }
}
