<?php

namespace App\Core\OAuth;

use App\Models\OAuthClient;
use Illuminate\Support\Str;

/**
 * Dynamic Client Registration (RFC 7591) for public clients: every client is registered with
 * token_endpoint_auth_method "none" whatever it asks for, and must use PKCE.
 */
class ClientRegistrar
{
    private const array GRANT_TYPES = ['authorization_code', 'refresh_token'];

    private const int MAX_REDIRECT_URIS = 10;

    public function __construct(private RedirectUriPolicy $redirectUris) {}

    /**
     * @param  array<array-key, mixed>  $metadata
     *
     * @throws OAuthException
     */
    public function register(array $metadata): OAuthClient
    {
        $redirectUris = $this->redirectUris($metadata['redirect_uris'] ?? null);
        $grantTypes = $this->grantTypes($metadata['grant_types'] ?? null);
        $this->ensureCodeResponseType($metadata['response_types'] ?? null);

        return OAuthClient::query()->create([
            'id' => 'smc_'.bin2hex(random_bytes(16)),
            'name' => $this->clientName($metadata['client_name'] ?? null),
            'redirect_uris' => $redirectUris,
            'grant_types' => $grantTypes,
            'token_endpoint_auth_method' => 'none',
            'scope' => $this->scope($metadata['scope'] ?? null),
            'client_uri' => $this->httpsUrl($metadata['client_uri'] ?? null),
            'logo_uri' => $this->httpsUrl($metadata['logo_uri'] ?? null),
            'registration_meta' => array_filter([
                'requested_token_endpoint_auth_method' => $this->shortString($metadata['token_endpoint_auth_method'] ?? null),
                'software_id' => $this->shortString($metadata['software_id'] ?? null),
                'software_version' => $this->shortString($metadata['software_version'] ?? null),
            ]) ?: null,
        ]);
    }

    /**
     * The client information response (RFC 7591 §3.2.1). No secret: these are public clients.
     *
     * @return array<string, mixed>
     */
    public function information(OAuthClient $client): array
    {
        return array_filter([
            'client_id' => $client->id,
            'client_id_issued_at' => $client->created_at?->getTimestamp(),
            'client_name' => $client->name,
            'redirect_uris' => $client->redirect_uris,
            'grant_types' => $client->grant_types,
            'response_types' => ['code'],
            'token_endpoint_auth_method' => $client->token_endpoint_auth_method,
            'scope' => $client->scope,
            'client_uri' => $client->client_uri,
            'logo_uri' => $client->logo_uri,
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * @return list<string>
     */
    private function redirectUris(mixed $uris): array
    {
        if (! is_array($uris) || ! array_is_list($uris) || $uris === []) {
            throw OAuthException::invalidRedirectUri('redirect_uris must list at least one redirect URI.');
        }

        if (count($uris) > self::MAX_REDIRECT_URIS) {
            throw OAuthException::invalidRedirectUri('Register at most '.self::MAX_REDIRECT_URIS.' redirect URIs.');
        }

        foreach ($uris as $uri) {
            if (! is_string($uri) || ! $this->redirectUris->allows($uri)) {
                $shown = is_string($uri) ? Str::limit($uri, 200) : 'A non-string value';

                throw OAuthException::invalidRedirectUri("Redirect URI {$shown} is not allowed. {$this->redirectUris->describe()}");
            }
        }

        return array_values(array_unique($uris));
    }

    /**
     * @return list<string>
     */
    private function grantTypes(mixed $grantTypes): array
    {
        if ($grantTypes === null) {
            return self::GRANT_TYPES;
        }

        if (! $this->isListOfStrings($grantTypes)) {
            throw OAuthException::invalidClientMetadata('grant_types must be a list of strings.');
        }

        $unsupported = array_diff($grantTypes, self::GRANT_TYPES);

        if ($unsupported !== []) {
            throw OAuthException::invalidClientMetadata('Unsupported grant type: '.implode(', ', $unsupported).'. Supported: '.implode(', ', self::GRANT_TYPES).'.');
        }

        if (! in_array('authorization_code', $grantTypes, true)) {
            throw OAuthException::invalidClientMetadata('grant_types must include authorization_code.');
        }

        return array_values(array_intersect(self::GRANT_TYPES, $grantTypes));
    }

    private function ensureCodeResponseType(mixed $responseTypes): void
    {
        if ($responseTypes === null) {
            return;
        }

        if (! $this->isListOfStrings($responseTypes) || array_diff($responseTypes, ['code']) !== []) {
            throw OAuthException::invalidClientMetadata('Only the "code" response type is supported.');
        }
    }

    /**
     * Self-declared and unverified: control and formatting characters (bidi overrides included) are
     * removed, whitespace collapsed, and the result capped at 100 characters. Views escape it again.
     */
    private function clientName(mixed $name): string
    {
        if ($name !== null && ! is_string($name)) {
            throw OAuthException::invalidClientMetadata('client_name must be a string.');
        }

        $clean = preg_replace('/\p{C}+/u', ' ', (string) $name) ?? '';
        $clean = trim(mb_substr(trim(preg_replace('/\s+/u', ' ', $clean) ?? ''), 0, 100));

        return $clean === '' ? 'Unnamed app' : $clean;
    }

    /**
     * Known scopes only; unknown ones are dropped rather than failing the registration.
     */
    private function scope(mixed $scope): ?string
    {
        $known = is_string($scope) ? Scopes::normalize(Scopes::parse($scope)) : [];

        return $known === [] ? null : implode(' ', $known);
    }

    private function httpsUrl(mixed $url): ?string
    {
        if (! is_string($url) || strlen($url) > 255 || ! str_starts_with($url, 'https://') || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return $url;
    }

    private function shortString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? mb_substr($value, 0, 100) : null;
    }

    /**
     * @phpstan-assert-if-true list<string> $value
     */
    private function isListOfStrings(mixed $value): bool
    {
        return is_array($value) && array_is_list($value) && $value !== [] && array_filter($value, fn (mixed $item): bool => ! is_string($item)) === [];
    }
}
