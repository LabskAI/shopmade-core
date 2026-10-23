<?php

use App\Core\Packages\Hooks;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;

it('shows the client only their own customers on the portal home', function () {
    $mine = Customer::factory()->create(['name' => 'Mine Customer']);
    Customer::factory()->create(['name' => 'Other Customer']);

    $this->actingAs(portalClient($mine))
        ->get(route('portal.home'))
        ->assertOk()
        ->assertSee('Mine Customer')
        ->assertDontSee('Other Customer');
});

it('renders portal tabs and sections that a package registers', function () {
    View::addNamespace('demo', base_path('tests/Fixtures/views'));
    Route::get('portal/demo', fn () => 'demo')->name('portal.demo.index');
    Route::getRoutes()->refreshNameLookups();

    $hooks = app(Hooks::class);
    $hooks->portalTab('Demo tab', 'portal.demo.index', 'portal.demo.*');
    $hooks->portalTab('Missing tab', 'portal.missing.index');
    $hooks->portalSection('demo::section', fn (User $user): array => ['who' => $user->name]);

    $client = portalClient(Customer::factory()->create());
    $client->update(['name' => 'Section Viewer']);

    $this->actingAs($client)
        ->get(route('portal.home'))
        ->assertOk()
        ->assertSee('Demo tab')
        ->assertDontSee('Missing tab')
        ->assertSee('Section for Section Viewer');
});

it('keeps staff out of the portal', function () {
    $staff = User::factory()->user()->create();

    $this->actingAs($staff)->get(route('portal.home'))->assertForbidden();
});
