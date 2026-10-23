<?php

namespace App\Http\Controllers\OAuth;

use App\Core\OAuth\OAuthUrls;
use App\Core\OAuth\Scopes;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class AuthorizationServerMetadataController extends Controller
{
    /**
     * RFC 8414 metadata, also served as /.well-known/openid-configuration for clients that only try that.
     */
    public function __invoke(): JsonResponse
    {
        $issuer = OAuthUrls::issuer();

        return response()->json([
            'issuer' => $issuer,
            'authorization_endpoint' => $issuer.route('oauth.authorize', absolute: false),
            'token_endpoint' => $issuer.route('oauth.token', absolute: false),
            'registration_endpoint' => $issuer.route('oauth.register', absolute: false),
            'revocation_endpoint' => $issuer.route('oauth.revoke', absolute: false),
            'response_types_supported' => ['code'],
            'response_modes_supported' => ['query'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'code_challenge_methods_supported' => ['S256'],
            'token_endpoint_auth_methods_supported' => ['none'],
            'revocation_endpoint_auth_methods_supported' => ['none'],
            'scopes_supported' => Scopes::all(),
            'authorization_response_iss_parameter_supported' => true,
        ], options: JSON_UNESCAPED_SLASHES);
    }
}
