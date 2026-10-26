<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('promotes the named user to super admin and leaves others alone', function () {
    $owner = User::factory()->admin()->create(['email' => 'owner@example.com']);
    $admin = User::factory()->admin()->create(['email' => 'admin@example.com']);

    $this->artisan('user:promote-super-admin', ['email' => 'owner@example.com'])
        ->assertSuccessful()
        ->expectsOutput('Promoted owner@example.com to super_admin.');

    expect($owner->fresh()->role)->toBe(UserRole::SuperAdmin)
        ->and($admin->fresh()->role)->toBe(UserRole::Admin);
});

it('fails when the user does not exist', function () {
    $this->artisan('user:promote-super-admin', ['email' => 'missing@example.com'])
        ->assertFailed();
});

it('creates the first owner with a password that works', function () {
    $this->artisan('user:create-owner', ['email' => 'Owner@Example.com'])->assertSuccessful();

    $owner = User::query()->where('email', 'owner@example.com')->firstOrFail();

    expect($owner->role)->toBe(UserRole::SuperAdmin)
        ->and(Hash::needsRehash($owner->password))->toBeFalse();
});

it('refuses to create an owner over an existing user', function () {
    User::factory()->create(['email' => 'owner@example.com']);

    $this->artisan('user:create-owner', ['email' => 'owner@example.com'])->assertFailed();
});
