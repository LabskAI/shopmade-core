<?php

namespace App\Core\OAuth;

use App\Models\OAuthClient;
use Illuminate\Http\RedirectResponse;

/**
 * A validated authorization request (see AuthorizationRequests::parse()).
 */
final readonly class AuthorizationRequest
{
    /**
     * @param  list<string>  $scopes  requested scopes, canonical order (default director)
     * @param  string|null  $resource  OAuthUrls::mcpResource() when the client sent a resource
     */
    public function __construct(
        public OAuthClient $client,
        public string $redirectUri,
        public ?string $state,
        public array $scopes,
        public string $codeChallenge,
        public ?string $resource,
    ) {}

    /**
     * Hidden fields that carry the request through the consent form; the POST validates them again.
     *
     * @return array<string, string>
     */
    public function formFields(): array
    {
        return array_filter([
            'response_type' => 'code',
            'client_id' => $this->client->id,
            'redirect_uri' => $this->redirectUri,
            'state' => $this->state,
            'scope' => implode(' ', $this->scopes),
            'code_challenge' => $this->codeChallenge,
            'code_challenge_method' => 'S256',
            'resource' => $this->resource,
        ], fn (?string $value): bool => $value !== null);
    }

    /**
     * The client's callback with the authorization response (code or error), state and iss.
     *
     * @param  array<string, string>  $parameters
     */
    public function callbackUrl(array $parameters): string
    {
        return OAuthUrls::callback($this->redirectUri, [...$parameters, 'state' => $this->state]);
    }

    /**
     * @param  array<string, string>  $parameters
     */
    public function respond(array $parameters): RedirectResponse
    {
        return redirect()->away($this->callbackUrl($parameters))->withHeaders(['Cache-Control' => 'no-store']);
    }

    public function redirectHost(): string
    {
        $host = (string) parse_url($this->redirectUri, PHP_URL_HOST);
        $port = parse_url($this->redirectUri, PHP_URL_PORT);

        return is_int($port) ? $host.':'.$port : $host;
    }

    public function isLoopback(): bool
    {
        return in_array(parse_url($this->redirectUri, PHP_URL_HOST), ['localhost', '127.0.0.1'], true);
    }
}
