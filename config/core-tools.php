<?php

return [
    [
        'slug' => 'dashboard',
        'label' => 'Dashboard',
        'group' => 'Work',
        'route' => 'dashboard',
        'pattern' => 'dashboard',
        'view_roles' => ['super_admin', 'admin', 'user'],
        'manage_roles' => ['super_admin', 'admin', 'user'],
    ],
    [
        'slug' => 'customers',
        'label' => 'Customers',
        'group' => 'People',
        'route' => 'customers.index',
        'pattern' => 'customers.*',
        'view_roles' => ['super_admin', 'admin', 'user'],
        'manage_roles' => ['super_admin', 'admin', 'user'],
    ],
    [
        'slug' => 'client-requests',
        'label' => 'Client requests',
        'group' => 'People',
        'route' => 'client-requests.index',
        'pattern' => 'client-requests.*',
        'view_roles' => ['user', 'admin'],
        'manage_roles' => ['user', 'admin'],
    ],
];
