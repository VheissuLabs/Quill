<?php

use App\Models\Organization;
use App\Models\Role;
use Database\Seeders\RoleSeeder;

test('the seeded templates are unscoped and every organization gets a copy', function () {
    new RoleSeeder()->run();

    $templates = Role::whereNull('organization_id')->pluck('name');

    expect($templates)->toContain('owner', 'admin', 'member', 'client');

    $organization = Organization::factory()->create();

    expect(Role::where('organization_id', $organization->id)->pluck('name')->sort()->values()->all())
        ->toBe($templates->sort()->values()->all());
})->note('An owner reshaping their own roles must not reshape anyone else\'s.');

test('owner is granted the whole catalogue and admin cannot delete the organization', function () {
    $organization = Organization::factory()->create();

    $granted = fn (string $role) => Role::where('organization_id', $organization->id)
        ->where('name', $role)
        ->sole()
        ->permissions
        ->pluck('name');

    expect($granted('owner'))->toContain('organization:delete', 'client:delete', 'team:delete');
    expect($granted('admin'))
        ->toContain('organization:update')
        ->not->toContain('organization:delete');
    expect($granted('member'))->toBeEmpty();
    expect($granted('client'))->toBeEmpty();
});

test('a role edited in one organization leaves the other alone', function () {
    $mine = Organization::factory()->create();
    $theirs = Organization::factory()->create();

    Role::where('organization_id', $mine->id)->where('name', 'member')->sole()
        ->syncPermissions(['client:create']);

    $theirMember = Role::where('organization_id', $theirs->id)->where('name', 'member')->sole();

    expect($theirMember->permissions)->toBeEmpty();
});
