<?php

namespace App\Core\OAuth;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * An error from a machine endpoint (token, registration, revocation), rendered as the JSON body of
 * RFC 6749 §5.2 / RFC 7591 §3.2.2 with Cache-Control: no-store.
 */
class OAuthException extends RuntimeException implements ShouldntReport
{
    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public readonly string $error,
        string $description,
        public readonly int $status = 400,
        private readonly array $headers = [],
    ) {
        parent::__construct($description);
    }

    public static function invalidRequest(string $description): self
    {
        return new self('invalid_request', $description);
    }

    public static function invalidClient(string $description): self
    {
        return new self('invalid_client', $description, 401);
    }

    public static function invalidGrant(string $description): self
    {
        return new self('invalid_grant', $description);
    }

    public static function invalidScope(string $description): self
    {
        return new self('invalid_scope', $description);
    }

    public static function invalidTarget(string $description): self
    {
        return new self('invalid_target', $description);
    }

    public static function unauthorizedClient(string $description): self
    {
        return new self('unauthorized_client', $description);
    }

    public static function unsupportedGrantType(string $description): self
    {
        return new self('unsupported_grant_type', $description);
    }

    public static function invalidRedirectUri(string $description): self
    {
        return new self('invalid_redirect_uri', $description);
    }

    public static function invalidClientMetadata(string $description): self
    {
        return new self('invalid_client_metadata', $description);
    }

    public function render(): JsonResponse
    {
        return response()->json(
            ['error' => $this->error, 'error_description' => $this->getMessage()],
            $this->status,
            [...$this->headers, 'Cache-Control' => 'no-store', 'Pragma' => 'no-cache'],
            JSON_UNESCAPED_SLASHES,
        );
    }
}
