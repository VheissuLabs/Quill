<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;

test('an organization generates a slug from its name', function () {
    $organization = Organization::factory()->create(['name' => 'Notary Dash']);

    expect($organization->slug)->toBe('notary-dash');
});

test('organization slugs use the next available suffix', function () {
    Organization::factory()->create(['name' => 'Acme']);
    Organization::factory()->create(['name' => 'Acme']);

    $organization = Organization::factory()->create(['name' => 'Acme']);

    expect($organization->slug)->toBe('acme-2');
});

test('renaming an organization regenerates its slug', function () {
    $organization = Organization::factory()->create(['name' => 'Before']);

    $organization->update(['name' => 'After']);

    expect($organization->fresh()->slug)->toBe('after');
});

test('an organization is resolved by slug', function () {
    expect((new Organization)->getRouteKeyName())->toBe('slug');
});

test('an organization has a uuid key', function () {
    $organization = Organization::factory()->create();

    expect(Str::isUuid($organization->id))->toBeTrue();
});

test('members attach with a role that casts to the enum', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();

    $organization->members()->attach($user, ['role' => OrganizationRole::Admin->value]);

    expect($organization->members)->toHaveCount(1);
    expect($organization->memberships->first()->role)->toBe(OrganizationRole::Admin);
    expect(Str::isUuid($organization->memberships->first()->id))->toBeTrue();
});

test('the owner is the member holding the owner role', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->create();
    $member = User::factory()->create();

    $organization->members()->attach($owner, ['role' => OrganizationRole::Owner->value]);
    $organization->members()->attach($member, ['role' => OrganizationRole::Member->value]);

    expect($organization->owner()->id)->toBe($owner->id);
});

test('organizations soft delete', function () {
    $organization = Organization::factory()->create();

    $organization->delete();

    $this->assertSoftDeleted('organizations', ['id' => $organization->id]);
});
