<?php

namespace App\Core\OAuth;

use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * An authorization endpoint error (RFC 6749 §4.1.2.1). While the client or its redirect URI is not
 * trusted the user sees an error page and is never redirected; afterwards the error goes back to the
 * client's redirect URI with state and iss.
 */
class AuthorizationError extends RuntimeException implements ShouldntReport
{
    private function __construct(
        string $description,
        public readonly string $error = 'invalid_request',
        public readonly ?string $redirectUri = null,
        public readonly ?string $state = null,
    ) {
        parent::__construct($description);
    }

    public static function untrusted(string $description): self
    {
        return new self($description);
    }

    public static function redirect(string $error, string $description, string $redirectUri, ?string $state): self
    {
        return new self($description, $error, $redirectUri, $state);
    }

    public function render(): Response
    {
        if ($this->redirectUri === null) {
            return response()->view('oauth.error', [
                'title' => 'This connection request cannot continue',
                'message' => $this->getMessage(),
            ], 400);
        }

        return redirect()->away(OAuthUrls::callback($this->redirectUri, [
            'error' => $this->error,
            'error_description' => $this->getMessage(),
            'state' => $this->state,
        ]))->withHeaders(['Cache-Control' => 'no-store']);
    }
}
