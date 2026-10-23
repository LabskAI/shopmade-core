<?php

use App\Models\Setting;

it('stores a json value by key', function () {
    Setting::put('org.name', 'Shopmade');
    Setting::put('budget.per_change_usd', 10);

    expect(Setting::get('org.name'))->toBe('Shopmade')
        ->and(Setting::get('budget.per_change_usd'))->toBe(10);
});

it('returns the default when the key is missing', function () {
    expect(Setting::get('org.logo', '/images/logo.svg'))->toBe('/images/logo.svg');
});
