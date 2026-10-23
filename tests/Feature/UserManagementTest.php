<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('lets a user change their own password', function () {
    $user = User::factory()->user()->create(['password' => 'old-password']);

    $this->actingAs($user)
        ->put(route('account.password.update'), [
            'current_password' => 'old-password',
            'password' => 'new-password-1',
            'password_confirmation' => 'new-password-1',
        ])
        ->assertRedirect(route('account.edit'))
        ->assertSessionHasNoErrors();

    expect(Hash::check('new-password-1', $user->fresh()->password))->toBeTrue();
});

it('refuses a password change without the current password', function () {
    $user = User::factory()->client()->create(['password' => 'old-password']);

    $this->actingAs($user)->get(route('account.edit'))->assertOk();

    $this->actingAs($user)
        ->put(route('account.password.update'), [
            'current_password' => 'wrong',
            'password' => 'new-password-1',
            'password_confirmation' => 'new-password-1',
        ])
        ->assertSessionHasErrors('current_password');

    expect(Hash::check('old-password', $user->fresh()->password))->toBeTrue();
});

it('lets an admin edit a staff member and reset their password', function () {
    $admin = User::factory()->admin()->create();
    $staff = User::factory()->user()->create();

    $this->actingAs($admin)->get(route('users.edit', $staff))->assertOk();

    $this->actingAs($admin)
        ->put(route('users.update', $staff), [
            'name' => 'Renamed',
            'email' => $staff->email,
            'role' => 'client',
            'password' => 'reset-password',
        ])
        ->assertRedirect(route('users.index'));

    $staff->refresh();

    expect($staff->name)->toBe('Renamed')
        ->and($staff->role)->toBe(UserRole::Client)
        ->and(Hash::check('reset-password', $staff->password))->toBeTrue();
});

it('keeps admins from editing admins or making admins', function () {
    $admin = User::factory()->admin()->create();
    $other = User::factory()->admin()->create();
    $staff = User::factory()->user()->create();

    $this->actingAs($admin)->get(route('users.edit', $other))->assertForbidden();
    $this->actingAs($admin)->post(route('users.deactivate', $other))->assertForbidden();

    $this->actingAs($admin)
        ->put(route('users.update', $staff), ['name' => $staff->name, 'email' => $staff->email, 'role' => 'admin'])
        ->assertSessionHasErrors('role');
});

it('refuses to let a user change their own role or deactivate themselves', function () {
    $owner = User::factory()->superAdmin()->create();

    $this->actingAs($owner)
        ->put(route('users.update', $owner), ['name' => $owner->name, 'email' => $owner->email, 'role' => 'user'])
        ->assertSessionHasErrors('role');

    $this->actingAs($owner)->post(route('users.deactivate', $owner))->assertSessionHasErrors('user');

    expect($owner->fresh()->isDeactivated())->toBeFalse();
});

it('signs out, blocks sign-in and removes abilities of a deactivated user, and lets them back', function () {
    $admin = User::factory()->admin()->create();
    $staff = User::factory()->user()->create(['password' => 'staff-password']);

    $this->actingAs($admin)->post(route('users.deactivate', $staff))->assertRedirect(route('users.index'));

    $staff->refresh();

    expect($staff->can('staff.access'))->toBeFalse();

    $this->actingAs($staff)->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();

    $this->post(route('login'), ['email' => $staff->email, 'password' => 'staff-password'])->assertSessionHasErrors('email');
    $this->assertGuest();

    $this->actingAs($admin)->post(route('users.reactivate', $staff));

    $this->post(route('logout'));
    $this->post(route('login'), ['email' => $staff->email, 'password' => 'staff-password'])->assertRedirect(route('dashboard'));
});
