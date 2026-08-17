<?php

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

    $organization->members()->attach($user);

    $user->assignOrganizationRole($organization, 'admin');

    expect($organization->members)->toHaveCount(1);
    expect($user->organizationRoleName($organization))->toBe('admin');
    expect(Str::isUuid($organization->memberships->first()->id))->toBeTrue();
});

test('the owner is whoever the owner_id column names', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->create();
    $member = User::factory()->create();

    $organization->members()->attach($owner);
    $organization->members()->attach($member);
    $organization->update(['owner_id' => $owner->id]);

    expect($organization->owner->id)->toBe($owner->id);
})->note('Ownership is a column so it survives an owner renaming their own roles.');

test('organizations soft delete', function () {
    $organization = Organization::factory()->create();

    $organization->delete();

    $this->assertSoftDeleted('organizations', ['id' => $organization->id]);
});

test('a user belongs to organizations they are a member of', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $other = Organization::factory()->create();

    $organization->members()->attach($user);

    $user->assignOrganizationRole($organization, 'member');

    expect($user->belongsToOrganization($organization))->toBeTrue();
    expect($user->belongsToOrganization($other))->toBeFalse();
});

test('a users organization role is readable and owners are identified', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $organization = Organization::factory()->create();

    $organization->members()->attach($owner);

    $owner->assignOrganizationRole($organization, 'owner');
    $organization->members()->attach($member);
    $member->assignOrganizationRole($organization, 'member');

    $organization->update(['owner_id' => $owner->id]);

    expect($owner->organizationRoleName($organization))->toBe('owner');
    expect($organization->owner->is($owner))->toBeTrue();
    expect($organization->owner->is($member))->toBeFalse();
});

test('a non member has no role and no permissions', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    expect($user->organizationRole($organization))->toBeNull();
    expect($user->canInOrganization($organization, 'organization:update'))->toBeFalse();
});

test('client contacts are identified and hold no permissions', function () {
    $organization = Organization::factory()->create();
    $client = App\Models\Client::factory()->heldBy($organization)->create();

    $contact = contactFor($client, 'Lucy Contact');

    expect($contact->isClientContact($organization))->toBeTrue();
    expect($contact->canInOrganization($organization, 'organization:update'))->toBeFalse();
    expect($contact->canInOrganization($organization, 'invitation:create'))->toBeFalse();
});

test('members are not client contacts', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();

    $organization->members()->attach($member);

    $member->assignOrganizationRole($organization, 'member');

    expect($member->isClientContact($organization))->toBeFalse();
});

test('a user can list the organizations they belong to', function () {
    $user = User::factory()->create();
    $first = Organization::factory()->create();
    $second = Organization::factory()->create();
    Organization::factory()->create();

    $first->members()->attach($user);

    $user->assignOrganizationRole($first, 'owner');
    $second->members()->attach($user);
    $user->assignOrganizationRole($second, 'client');

    expect($user->organizations()->pluck('organizations.id')->sort()->values()->all())
        ->toBe(collect([$first->id, $second->id])->sort()->values()->all());
});
