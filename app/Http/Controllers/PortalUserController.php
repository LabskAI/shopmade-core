<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Mail\PortalAccessGrantedMail;
use App\Mail\PortalInviteMail;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class PortalUserController extends Controller
{
    public function store(Request $request, Customer $customer): RedirectResponse
    {
        abort_unless($request->user()?->can('users.manage'), 403);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $user = User::query()->firstOrNew(['email' => $data['email']]);

        if (! $user->exists) {
            $user->name = filled($data['name'] ?? null)
                ? $data['name']
                : str($data['email'])->before('@')->headline()->toString();
            $user->password = Str::password(32);
            $user->role = UserRole::Client;
            $user->must_set_password = true;
            $user->password_invite_version = 0;
            $user->save();
        } else {
            abort_unless($user->isClient() || $user->isSuperAdmin() || $user->isAdmin(), 422, 'That user cannot access the client portal.');
        }

        $customer->portalUsers()->syncWithoutDetaching([$user->id]);

        $hours = $this->inviteTtlHours();

        if ($user->mustSetPassword()) {
            $version = $user->issuePasswordInvite();

            $link = URL::temporarySignedRoute(
                'portal.password.create',
                now()->addHours($hours),
                ['user' => $user, 'v' => $version],
            );

            Mail::to($user)->send(new PortalInviteMail($user, $customer, $link, $hours));

            return redirect()->route('customers.show', $customer)
                ->with('status', "Portal invite sent. The set-password link expires in {$hours} hours.")
                ->with('portal_invite_link', $this->mailIsDelivered() ? null : $link);
        }

        Mail::to($user)->send(new PortalAccessGrantedMail($user, $customer));

        return redirect()->route('customers.show', $customer)
            ->with('status', 'Portal access granted. We emailed them about this customer.');
    }

    public function destroy(Request $request, Customer $customer, User $user): RedirectResponse
    {
        abort_unless($request->user()?->can('users.manage'), 403);
        $customer->portalUsers()->detach($user->id);

        return redirect()->route('customers.show', $customer)->with('status', 'Portal access removed.');
    }

    /**
     * False when the mailer only logs or keeps messages (the default install), so the admin is shown the link to pass on.
     */
    private function mailIsDelivered(): bool
    {
        return ! in_array(config('mail.default'), ['log', 'array'], true);
    }

    private function inviteTtlHours(): int
    {
        return max(1, (int) config('auth.portal_invite_ttl_hours'));
    }
}
