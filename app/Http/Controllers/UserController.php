<?php

namespace App\Http\Controllers;

use App\Core\Agents\Actor;
use App\Core\OAuth\ConnectorGrants;
use App\Enums\UserRole;
use App\Models\ConnectorGrant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        Gate::authorize('users.manage');

        $users = User::query()->orderBy('name')->orderBy('email')->get();

        return view('users.index', compact('users'));
    }

    public function create(Request $request): View
    {
        Gate::authorize('users.manage');

        return view('users.create', [
            'roles' => $this->assignableRoles($request->user()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('users.manage');

        $assignable = $this->assignableRoles($request->user())->map->value->all();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'string', Rule::in($assignable)],
        ]);

        if ($data['role'] === UserRole::Admin->value && ! $request->user()->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'role' => 'Only a super admin can create admins.',
            ]);
        }

        User::query()->create($data);

        return redirect()->route('users.index')->with('status', 'User created.');
    }

    public function edit(Request $request, User $user): View
    {
        Gate::authorize('users.manage');
        $this->ensureManageable($request->user(), $user);

        return view('users.edit', [
            'user' => $user,
            'roles' => $this->assignableRoles($request->user()),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('users.manage');
        $this->ensureManageable($request->user(), $user);

        $assignable = $this->assignableRoles($request->user())->map->value->all();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', 'string', Rule::in($assignable)],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        if ($user->is($request->user()) && $data['role'] !== $user->role->value) {
            throw ValidationException::withMessages(['role' => 'You cannot change your own role.']);
        }

        if ($user->is($request->user()) && filled($data['password'] ?? null)) {
            throw ValidationException::withMessages(['password' => 'Change your own password on My account.']);
        }

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        } else {
            $data['must_set_password'] = false;
            $data['remember_token'] = Str::random(60);
        }

        $user->forceFill($data)->save();

        return redirect()->route('users.index')->with('status', 'User saved.');
    }

    public function deactivate(Request $request, User $user, ConnectorGrants $grants): RedirectResponse
    {
        Gate::authorize('users.manage');
        $this->ensureManageable($request->user(), $user);

        if ($user->is($request->user())) {
            throw ValidationException::withMessages(['user' => 'You cannot deactivate yourself.']);
        }

        $user->forceFill(['deactivated_at' => now(), 'remember_token' => null])->save();

        ConnectorGrant::query()->where('user_id', $user->id)->whereNull('revoked_at')->get()
            ->each(fn (ConnectorGrant $grant) => $grants->revoke($grant, Actor::forUser($request->user())));

        return redirect()->route('users.index')->with('status', "{$user->name} can no longer sign in.");
    }

    public function reactivate(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('users.manage');
        $this->ensureManageable($request->user(), $user);

        $user->forceFill(['deactivated_at' => null])->save();

        return redirect()->route('users.index')->with('status', "{$user->name} can sign in again.");
    }

    /**
     * Only a super admin manages admins and super admins.
     */
    private function ensureManageable(User $actor, User $target): void
    {
        abort_if(! $actor->isSuperAdmin() && ($target->isSuperAdmin() || $target->isAdmin()), 403);
    }

    /**
     * @return Collection<int, UserRole>
     */
    private function assignableRoles(User $actor)
    {
        if ($actor->isSuperAdmin()) {
            return collect([
                UserRole::SuperAdmin,
                UserRole::Admin,
                UserRole::User,
                UserRole::Client,
            ]);
        }

        return collect([UserRole::User, UserRole::Client]);
    }
}
