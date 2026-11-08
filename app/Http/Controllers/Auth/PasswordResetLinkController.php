<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class PasswordResetLinkController extends Controller
{
    public const string STATUS = 'If that email has an account, we sent a link to reset the password.';

    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Always answers with the same status so the form cannot be used to find out which emails have accounts.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        Password::sendResetLink(['email' => $data['email']], function (User $user, string $token): void {
            if ($user->isDeactivated()) {
                return;
            }

            $user->sendPasswordResetNotification($token);
        });

        return back()->with('status', self::STATUS);
    }
}
