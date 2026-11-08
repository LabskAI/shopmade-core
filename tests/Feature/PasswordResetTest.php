<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

it('links to forgot password from the login page', function () {
    $this->get(route('login'))->assertOk()->assertSee(route('password.request'));
});

it('emails a reset link and answers the same for unknown emails', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email])
        ->assertSessionHas('status');
    $this->post(route('password.email'), ['email' => 'nobody@example.com'])
        ->assertSessionHas('status');

    Notification::assertSentTo($user, ResetPassword::class);
    Notification::assertSentTimes(ResetPassword::class, 1);
});

it('does not email deactivated users', function () {
    Notification::fake();
    $user = User::factory()->create(['deactivated_at' => now()]);

    $this->post(route('password.email'), ['email' => $user->email])->assertSessionHas('status');

    Notification::assertNothingSent();
});

it('resets the password with a valid token', function () {
    $user = User::factory()->create(['must_set_password' => true]);
    $token = Password::broker()->createToken($user);

    $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))->assertOk();

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'new-password-1',
        'password_confirmation' => 'new-password-1',
    ])->assertRedirect(route('login'));

    $user->refresh();
    expect(Hash::check('new-password-1', $user->password))->toBeTrue()
        ->and($user->mustSetPassword())->toBeFalse();
});

it('rejects a bad token', function () {
    $user = User::factory()->create();

    $this->post(route('password.update'), [
        'token' => 'wrong',
        'email' => $user->email,
        'password' => 'new-password-1',
        'password_confirmation' => 'new-password-1',
    ])->assertSessionHasErrors('email');

    expect(Hash::check('new-password-1', $user->fresh()->password))->toBeFalse();
});

it('prints a reset link from the command line', function () {
    $user = User::factory()->create();

    $this->artisan('user:reset-link', ['email' => $user->email])
        ->expectsOutputToContain('/reset-password/')
        ->assertSuccessful();
});
