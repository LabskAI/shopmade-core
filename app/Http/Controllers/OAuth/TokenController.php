<?php

namespace App\Http\Controllers\OAuth;

use App\Core\OAuth\OAuthException;
use App\Core\OAuth\TokenIssuer;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TokenController extends Controller
{
    /**
     * The token endpoint: parameters come from the body only (form-encoded per RFC 6749, or JSON).
     */
    public function store(Request $request, TokenIssuer $issuer): JsonResponse
    {
        $input = $request->post();
        $grantType = $input['grant_type'] ?? null;

        if (! is_string($grantType) || $grantType === '') {
            throw OAuthException::invalidRequest('grant_type is required.');
        }

        if (! in_array($grantType, ['authorization_code', 'refresh_token'], true)) {
            throw OAuthException::unsupportedGrantType('Supported grant types: authorization_code, refresh_token.');
        }

        $client = $issuer->client($input['client_id'] ?? $request->getUser());

        $tokens = $grantType === 'authorization_code'
            ? $issuer->exchangeAuthorizationCode($client, $input)
            : $issuer->refresh($client, $input);

        return response()->json($tokens, 200, ['Cache-Control' => 'no-store', 'Pragma' => 'no-cache'], JSON_UNESCAPED_SLASHES);
    }
}
