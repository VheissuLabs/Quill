<?php

uses(Tests\TestCase::class);

test('owner is granted the whole catalog', function () {
    expect(config('roles.defaults.owner'))->toBe('*');
});

test('admin may update the organization but not delete it', function () {
    expect(config('roles.defaults.admin'))
        ->toContain('organization:update')
        ->not->toContain('organization:delete');
});

test('member and client are granted nothing by default', function () {
    expect(config('roles.defaults.member'))->toBe([]);
    expect(config('roles.defaults.client'))->toBe([]);
});

test('every default grant names a permission in the catalog', function () {
    $catalog = config('roles.permissions');

    $granted = collect(config('roles.defaults'))
        ->reject(fn (array|string $permissions) => $permissions === '*')
        ->flatten()
        ->unique();

    expect($granted->diff($catalog)->all())->toBe([]);
});
