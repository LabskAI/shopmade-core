<?php

use App\Core\Packages\Hooks;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;

it('shows the core counts on the dashboard', function () {
    Customer::factory()->count(2)->create();

    $this->actingAs(User::factory()->user()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSeeInOrder(['Customers', '2', 'Client requests this week']);
});

it('renders the nav entries, dashboard cards and customer panels a package registers', function () {
    View::addNamespace('demo', base_path('tests/Fixtures/views'));

    $hooks = app(Hooks::class);
    $hooks->tool([
        'slug' => 'demo-tool',
        'label' => 'Demo package tool',
        'group' => 'Work',
        'route' => 'customers.index',
        'pattern' => 'customers.*',
        'view_roles' => ['user'],
        'manage_roles' => ['user'],
    ]);
    $hooks->dashboardCard('demo::card', fn (User $user): array => ['who' => $user->name]);
    $hooks->customerPanel('demo::panel', fn (Customer $customer): array => ['who' => $customer->name]);

    $customer = Customer::factory()->create(['name' => 'Panel Customer']);
    $user = User::factory()->user()->create(['name' => 'Card Viewer']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Demo package tool')
        ->assertSee('Card for Card Viewer');

    $this->actingAs($user)
        ->get(route('customers.show', $customer))
        ->assertOk()
        ->assertSee('Panel for Panel Customer');
});

it('refuses a nav entry without its role lists', function () {
    app(Hooks::class)->tool([
        'slug' => 'broken',
        'label' => 'Broken',
        'group' => 'Work',
        'route' => 'customers.index',
        'pattern' => 'customers.*',
    ]);
})->throws(InvalidArgumentException::class, "Core tool 'broken' needs 'view_roles' as a list of role names.");

it('draws a package state like the core state it maps to', function () {
    app(Hooks::class)->status('on_site', like: 'running', label: 'On site');

    $html = Blade::render('<x-status state="on_site" />');

    expect($html)->toContain('On site')->toContain('bg-warn-200');
});
