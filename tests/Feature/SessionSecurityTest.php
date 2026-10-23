<?php

use App\Models\ConnectorGrant;
use App\Models\OAuthAccessToken;
use App\Models\User;

require_once __DIR__.'/Agents/OAuth/OAuthHelpers.php';

it('builds https URLs behind a trusted proxy that terminated HTTPS', function () {
    config(['trustedproxy.proxies' => '*']);

    $this->get('/login', ['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'shop.example.com'])
        ->assertOk()
        ->assertSee('https://shop.example.com/login', false)
        ->assertDontSee('http://shop.example.com/', false);
});

it('ignores forwarded headers when no proxy is trusted', function () {
    config(['trustedproxy.proxies' => null]);

    $this->get('/login', ['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'shop.example.com'])
        ->assertOk()
        ->assertDontSee('https://shop.example.com/login', false);
});

it('signs out other sessions and rotates the remember token when an admin resets a password', function () {
    $admin = User::factory()->admin()->create();
    $staff = User::factory()->user()->create(['password' => 'old-password']);
    $rememberToken = $staff->remember_token;

    $this->post(route('login'), ['email' => $staff->email, 'password' => 'old-password'])->assertRedirect(route('dashboard'));
    $this->get(route('dashboard'))->assertOk();
    $staffSession = session()->all();

    $this->flushSession();
    $this->actingAs($admin)->put(route('users.update', $staff), [
        'name' => $staff->name,
        'email' => $staff->email,
        'role' => $staff->role->value,
        'password' => 'new-password',
    ])->assertRedirect(route('users.index'));

    expect($staff->refresh()->remember_token)->not->toBe($rememberToken);

    $this->flushSession();
    auth()->forgetGuards();
    $this->withSession($staffSession)->get(route('dashboard'))->assertRedirect(route('login'));
});

it('keeps the current session but rotates the remember token on a password change', function () {
    $user = User::factory()->user()->create(['password' => 'old-password']);
    $rememberToken = $user->remember_token;

    $this->post(route('login'), ['email' => $user->email, 'password' => 'old-password']);
    $this->put(route('account.password.update'), [
        'current_password' => 'old-password',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertRedirect(route('account.edit'));

    expect($user->refresh()->remember_token)->not->toBe($rememberToken);
    $this->get(route('dashboard'))->assertOk();
});

it('does not let a super admin set their own password from the user screen', function () {
    $owner = User::factory()->superAdmin()->create(['password' => 'owner-password']);

    $this->actingAs($owner)->put(route('users.update', $owner), [
        'name' => $owner->name,
        'email' => $owner->email,
        'role' => $owner->role->value,
        'password' => 'hijacked-password',
    ])->assertSessionHasErrors('password');

    $this->post(route('logout'));
    $this->post(route('login'), ['email' => $owner->email, 'password' => 'owner-password'])->assertRedirect(route('dashboard'));
});

it('revokes connector access and refuses refresh for a deactivated user', function () {
    oauthUseTestHost();
    $admin = User::factory()->admin()->create();
    $staff = User::factory()->user()->create();

    [$code, $plaintext] = oauthCreateCode(['user_id' => $staff->id]);
    $tokens = $this->post('/oauth/token', oauthCodeExchange($code, $plaintext))->assertOk()->json();

    $this->actingAs($admin)->post(route('users.deactivate', $staff))->assertRedirect(route('users.index'));

    expect(ConnectorGrant::query()->where('user_id', $staff->id)->whereNull('revoked_at')->exists())->toBeFalse()
        ->and(OAuthAccessToken::query()->where('user_id', $staff->id)->whereNull('revoked_at')->exists())->toBeFalse();

    $this->post('/oauth/token', [
        'grant_type' => 'refresh_token',
        'client_id' => $code->client_id,
        'refresh_token' => $tokens['refresh_token'],
    ])->assertStatus(400)->assertJsonPath('error', 'invalid_grant');
});
