<?php

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;

it('does not show seeded account credentials', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertDontSee('Demo accounts')
        ->assertDontSee('admin@shopmade.test')
        ->assertDontSee('owner@shopmade.test')
        ->assertDontSee('client@shopmade.test')
        ->assertDontSee('staff@shopmade.test')
        ->assertDontSee('password: password');
});

it('seeds the demo cast with one account per role', function () {
    $this->seed(DatabaseSeeder::class);

    $admin = User::query()->where('email', 'admin@shopmade.test')->first();

    expect(User::query()->count())->toBe(4)
        ->and(User::query()->where('email', 'owner@shopmade.test')->first()?->role)->toBe(UserRole::SuperAdmin)
        ->and($admin?->role)->toBe(UserRole::Admin)
        ->and(User::query()->where('email', 'staff@shopmade.test')->first()?->role)->toBe(UserRole::User)
        ->and(User::query()->where('email', 'client@shopmade.test')->first()?->role)->toBe(UserRole::Client);

    expect(Hash::check('password', $admin->password))->toBeTrue();
});

it('seeds Shopmade as the organization with demo customers', function () {
    $this->seed(DatabaseSeeder::class);

    expect(Setting::get('org.name'))->toBe('Shopmade')
        ->and(Customer::query()->where('address', 'like', '%Oakland, CA')->count())->toBeGreaterThan(0);
});

it('authenticates a seeded-style admin', function () {
    $admin = User::factory()->admin()->create([
        'email' => 'admin@shopmade.test',
    ]);

    $this->post(route('login'), [
        'email' => 'admin@shopmade.test',
        'password' => 'password',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($admin);
});

it('blocks users from the plugin admin', function () {
    $user = User::factory()->user()->create();

    $this->actingAs($user)
        ->get(route('admin.plugins.index'))
        ->assertForbidden();
});

it('blocks admins from assigning builder role', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('users.store'), [
            'name' => 'X',
            'email' => 'x@example.com',
            'password' => 'password123',
            'role' => 'builder',
        ])
        ->assertSessionHasErrors('role');
});

it('throttles repeated failed logins for one email', function () {
    User::factory()->admin()->create(['email' => 'admin@example.com']);

    foreach (range(1, 5) as $attempt) {
        $this->post(route('login'), ['email' => 'admin@example.com', 'password' => 'wrong'])->assertRedirect();
    }

    $this->post(route('login'), ['email' => 'admin@example.com', 'password' => 'wrong'])->assertTooManyRequests();
});
