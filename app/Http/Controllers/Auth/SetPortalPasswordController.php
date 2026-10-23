<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class SetPortalPasswordController extends Controller
{
    public function create(Request $request, User $user): View|Response
    {
        if (! $user->matchesPasswordInvite($request->integer('v'))) {
            return $this->rejectedInviteResponse($user);
        }

        return view('auth.set-portal-password', [
            'user' => $user,
            'actionUrl' => $request->fullUrl(),
        ]);
    }

    public function store(Request $request, User $user): RedirectResponse|Response
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! $user->consumePasswordInvite($request->integer('v'), $data['password'])) {
            return $this->rejectedInviteResponse($user->fresh() ?? $user);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Password set. You can sign in now.');
    }

    private function rejectedInviteResponse(User $user): Response
    {
        if (! $user->mustSetPassword()) {
            return response()->view('auth.portal-invite-invalid', [
                'message' => 'This invite link has already been used. Sign in with your password.',
            ], 403);
        }

        return response()->view('auth.portal-invite-invalid', [
            'message' => 'This invite link is invalid or has expired.',
        ], 403);
    }
}
