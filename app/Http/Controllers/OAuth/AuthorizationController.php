<?php

namespace App\Http\Controllers\OAuth;

use App\Core\OAuth\AuthorizationCodes;
use App\Core\OAuth\AuthorizationRequest;
use App\Core\OAuth\AuthorizationRequests;
use App\Core\OAuth\Consent;
use App\Core\OAuth\Scopes;
use App\Enums\ConnectorScope;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;

class AuthorizationController extends Controller
{
    /**
     * The consent screen must never render inside another site's frame (clickjacking) or be cached.
     */
    private const array PAGE_HEADERS = [
        'X-Frame-Options' => 'DENY',
        'Content-Security-Policy' => "frame-ancestors 'none'",
        'Cache-Control' => 'no-store',
    ];

    public function show(Request $request, AuthorizationRequests $requests): Response
    {
        $authorization = $requests->parse($request->query());
        $user = $request->user();
        $consent = Consent::for($authorization, $user);

        if (! $consent->isPossible()) {
            return $this->denied($authorization, $user);
        }

        return $this->consentScreen($authorization, $consent, $user);
    }

    public function store(Request $request, AuthorizationRequests $requests, AuthorizationCodes $codes): Response|RedirectResponse
    {
        $authorization = $requests->parse($request->post());

        if ($request->input('decision') !== 'approve') {
            return $authorization->respond(['error' => 'access_denied', 'error_description' => 'The user denied the request.']);
        }

        $user = $request->user();
        $consent = Consent::for($authorization, $user);

        if (! $consent->isPossible()) {
            return $this->denied($authorization, $user);
        }

        $scopes = $consent->approve(Arr::wrap($request->input('optional_scopes')));

        if ($scopes === []) {
            return $this->consentScreen($authorization, $consent, $user, 'Tick at least one permission, or choose Deny.');
        }

        return $authorization->respond(['code' => $codes->issue($authorization, $user, $scopes)]);
    }

    private function consentScreen(AuthorizationRequest $authorization, Consent $consent, User $user, ?string $error = null): Response
    {
        return response()->view('oauth.authorize', [
            'clientName' => $authorization->client->name,
            'redirectHost' => $authorization->redirectHost(),
            'isLoopback' => $authorization->isLoopback(),
            'user' => $user,
            'roleLabel' => $user->role->label(),
            'fields' => $authorization->formFields(),
            'granted' => $this->describe($consent->granted),
            'optional' => $this->describe($consent->optional),
            'unavailable' => $this->describe($consent->unavailable),
            'error' => $error,
        ], $error === null ? 200 : 422)->withHeaders(self::PAGE_HEADERS);
    }

    private function denied(AuthorizationRequest $authorization, User $user): Response
    {
        return response()->view('oauth.error', [
            'title' => 'Access denied',
            'message' => sprintf(
                'Your account (%s) cannot give “%s” the %s access it asked for. Only users who can edit the website (super admins and admins) can connect.',
                $user->role->label(),
                $authorization->client->name,
                implode(', ', $authorization->scopes),
            ),
            'returnUrl' => $authorization->callbackUrl([
                'error' => 'access_denied',
                'error_description' => 'The signed-in user may not grant the requested scopes.',
            ]),
            'returnHost' => $authorization->redirectHost(),
        ], 403)->withHeaders(self::PAGE_HEADERS);
    }

    /**
     * @param  list<string>  $scopes
     * @return list<array{name: string, label: string, description: string}>
     */
    private function describe(array $scopes): array
    {
        return array_map(fn (string $scope): array => [
            'name' => $scope,
            'label' => ConnectorScope::from($scope)->label(),
            'description' => Scopes::describe($scope),
        ], $scopes);
    }
}
