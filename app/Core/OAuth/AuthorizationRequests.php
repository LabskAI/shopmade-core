<?php

namespace App\Core\OAuth;

use App\Enums\ConnectorScope;
use App\Models\OAuthClient;

class AuthorizationRequests
{
    public function __construct(private RedirectUriPolicy $redirectUris) {}

    /**
     * Validates an authorization request: a registered client and one of its redirect URIs first (errors
     * shown to the user), then response_type=code, PKCE S256, scope (default director) and resource
     * (errors sent back to the client).
     *
     * @param  array<array-key, mixed>  $input
     *
     * @throws AuthorizationError
     */
    public function parse(array $input): AuthorizationRequest
    {
        $client = $this->client($input['client_id'] ?? null);
        $redirectUri = $this->redirectUri($client, $input['redirect_uri'] ?? null);
        $state = is_string($input['state'] ?? null) ? $input['state'] : null;

        $fail = fn (string $error, string $description): AuthorizationError => AuthorizationError::redirect($error, $description, $redirectUri, $state);

        $responseType = $input['response_type'] ?? null;

        if ($responseType === null) {
            throw $fail('invalid_request', 'response_type is required.');
        }

        if ($responseType !== 'code') {
            throw $fail('unsupported_response_type', 'Only response_type=code is supported.');
        }

        $challenge = $input['code_challenge'] ?? null;

        if (! is_string($challenge)) {
            throw $fail('invalid_request', 'PKCE is required: send code_challenge with code_challenge_method=S256.');
        }

        if (($input['code_challenge_method'] ?? null) !== 'S256') {
            throw $fail('invalid_request', 'code_challenge_method must be S256.');
        }

        if (! Pkce::isWellFormed($challenge)) {
            throw $fail('invalid_request', 'code_challenge must be 43 to 128 characters of A-Z, a-z, 0-9, "-", ".", "_" or "~".');
        }

        $scope = $input['scope'] ?? null;

        if ($scope !== null && ! is_string($scope)) {
            throw $fail('invalid_scope', 'scope must be a space-separated string.');
        }

        $requested = Scopes::parse($scope);
        $unknown = array_diff($requested, Scopes::all());

        if ($unknown !== []) {
            throw $fail('invalid_scope', 'Unknown scope: '.implode(', ', $unknown).'. Supported: '.implode(', ', Scopes::all()).'.');
        }

        $resource = $input['resource'] ?? null;

        if ($resource !== null && (! is_string($resource) || ! OAuthUrls::isMcpResource($resource))) {
            throw $fail('invalid_target', 'resource must be '.OAuthUrls::mcpResource().'.');
        }

        return new AuthorizationRequest(
            client: $client,
            redirectUri: $redirectUri,
            state: $state,
            scopes: $requested === [] ? [ConnectorScope::Director->value] : Scopes::normalize($requested),
            codeChallenge: $challenge,
            resource: $resource === null ? null : OAuthUrls::mcpResource(),
        );
    }

    private function client(mixed $clientId): OAuthClient
    {
        if (! is_string($clientId) || $clientId === '') {
            throw AuthorizationError::untrusted('The request does not say which app is asking (client_id is missing).');
        }

        $client = strlen($clientId) <= 64 ? OAuthClient::query()->find($clientId) : null;

        if ($client === null || $client->revoked_at !== null) {
            throw AuthorizationError::untrusted('This app is not registered here, or its registration was revoked. Remove the connector in Claude and add it again.');
        }

        return $client;
    }

    private function redirectUri(OAuthClient $client, mixed $redirectUri): string
    {
        if (! is_string($redirectUri) || $redirectUri === '') {
            throw AuthorizationError::untrusted('The request does not say where to send you back (redirect_uri is missing).');
        }

        if (! in_array($redirectUri, $client->redirect_uris ?? [], true) || ! $this->redirectUris->allows($redirectUri)) {
            throw AuthorizationError::untrusted('The return address in this request is not registered for this app, so you will not be sent there.');
        }

        return $redirectUri;
    }
}
