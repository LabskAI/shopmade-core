<?php

namespace App\Http\Controllers\Admin;

use App\Core\Agents\Actor;
use App\Core\Agents\AgentRefusedException;
use App\Core\OAuth\ConnectorGrants;
use App\Core\OAuth\OAuthUrls;
use App\Core\OAuth\Scopes;
use App\Http\Controllers\Controller;
use App\Models\ConnectorGrant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

class ConnectorController extends Controller
{
    public function index(ConnectorGrants $grants): View
    {
        $activeGrants = $grants->active()->loadMissing(['user', 'client']);

        return view('admin.connectors.index', [
            'grants' => $activeGrants,
            'eligibleScopes' => $activeGrants->mapWithKeys(fn (ConnectorGrant $grant): array => [$grant->id => Scopes::allowedFor($grant->user)])->all(),
            'scopeDescriptions' => collect(Scopes::all())->mapWithKeys(fn (string $scope): array => [$scope => Scopes::describe($scope)])->all(),
            'mcpUrl' => OAuthUrls::mcpResource(),
            'connectorName' => 'Shopmade '.config('agents.company'),
        ]);
    }

    public function update(Request $request, ConnectorGrant $grant, ConnectorGrants $grants): RedirectResponse
    {
        if (! $grant->isActive()) {
            return redirect()->route('admin.connectors.index')->withErrors(['scopes' => 'This connector was already revoked.']);
        }

        $validated = $request->validate([
            'scopes' => ['required', 'array'],
            'scopes.*' => ['string', 'distinct', Rule::in(Scopes::allowedFor($grant->user))],
        ], [
            'scopes.required' => 'Keep at least one scope. To remove all access, revoke the connector.',
            'scopes.*.in' => ':input is not a scope this user may grant.',
        ]);

        try {
            $grants->changeScopes($grant, array_values($validated['scopes']), Actor::forUser($request->user()));
        } catch (AgentRefusedException|InvalidArgumentException $exception) {
            return redirect()->route('admin.connectors.index')->withErrors(['scopes' => $exception->getMessage()]);
        }

        return redirect()->route('admin.connectors.index')->with('status', 'Scopes updated. Tokens already issued follow the new scopes on their next call.');
    }

    public function destroy(Request $request, ConnectorGrant $grant, ConnectorGrants $grants): RedirectResponse
    {
        if (! $grant->isActive()) {
            return redirect()->route('admin.connectors.index')->with('status', 'This connector was already revoked.');
        }

        try {
            $grants->revoke($grant, Actor::forUser($request->user()));
        } catch (AgentRefusedException|InvalidArgumentException $exception) {
            return redirect()->route('admin.connectors.index')->withErrors(['scopes' => $exception->getMessage()]);
        }

        return redirect()->route('admin.connectors.index')->with('status', 'Connector revoked. Its tokens stop working immediately.');
    }
}
