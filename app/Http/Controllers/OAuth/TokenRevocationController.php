<?php

namespace App\Http\Controllers\OAuth;

use App\Core\OAuth\OAuthException;
use App\Core\OAuth\TokenRevoker;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TokenRevocationController extends Controller
{
    /**
     * RFC 7009: 200 whether or not the token existed, so the endpoint reveals nothing about tokens.
     */
    public function store(Request $request, TokenRevoker $revoker): Response
    {
        $token = $request->post('token');

        if (! is_string($token) || $token === '') {
            throw OAuthException::invalidRequest('token is required.');
        }

        $hint = $request->post('token_type_hint');
        $clientId = $request->post('client_id') ?? $request->getUser();

        $revoker->revoke($token, is_string($hint) ? $hint : null, is_string($clientId) ? $clientId : null);

        return response('', 200, ['Cache-Control' => 'no-store', 'Pragma' => 'no-cache']);
    }
}
