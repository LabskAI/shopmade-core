<?php

namespace App\Http\Controllers\OAuth;

use App\Core\OAuth\ClientRegistrar;
use App\Core\OAuth\OAuthException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class ClientRegistrationController extends Controller
{
    private const int REGISTRATIONS_PER_HOUR = 20;

    /**
     * RFC 7591 Dynamic Client Registration (JSON body), limited per IP address.
     */
    public function store(Request $request, ClientRegistrar $registrar): JsonResponse
    {
        $key = 'oauth-register:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::REGISTRATIONS_PER_HOUR)) {
            throw new OAuthException(
                'temporarily_unavailable',
                'Too many client registrations from this address. Try again later.',
                429,
                ['Retry-After' => (string) RateLimiter::availableIn($key)],
            );
        }

        RateLimiter::hit($key, 3600);

        $metadata = json_decode($request->getContent(), true);

        if (! is_array($metadata) || ($metadata !== [] && array_is_list($metadata))) {
            throw OAuthException::invalidClientMetadata('Send the client metadata as a JSON object.');
        }

        $client = $registrar->register($metadata);

        return response()->json(
            $registrar->information($client),
            201,
            ['Cache-Control' => 'no-store', 'Pragma' => 'no-cache'],
            JSON_UNESCAPED_SLASHES,
        );
    }
}
