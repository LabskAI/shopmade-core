<?php

use App\Models\User;
use App\Support\Capabilities;

it('grants each capability to the roles in config', function (string $state, array $allowed) {
    $user = User::factory()->{$state}()->create();

    $granted = array_values(array_filter(
        [Capabilities::UsersManage, Capabilities::PortalAccess, Capabilities::StaffAccess, Capabilities::SiteEdit, Capabilities::ConnectorsManage],
        fn (string $capability): bool => $user->can($capability),
    ));

    expect($granted)->toBe($allowed);
})->with([
    'super admin' => ['superAdmin', ['users.manage', 'staff.access', 'site.edit', 'connectors.manage']],
    'admin' => ['admin', ['users.manage', 'staff.access', 'site.edit', 'connectors.manage']],
    'user' => ['user', ['staff.access']],
    'client' => ['client', ['portal.access']],
]);

it('lets admins manage plugins and the site but not staff users', function (string $state, bool $allowed) {
    $user = User::factory()->{$state}()->create();

    expect($user->can('manage-plugins'))->toBe($allowed)
        ->and($user->can('manage-site'))->toBe($allowed);
})->with([
    'super admin' => ['superAdmin', true],
    'admin' => ['admin', true],
    'user' => ['user', false],
]);
