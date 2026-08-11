<?php

use App\Enums\OrganizationPermission;
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

test('a user belongs to organizations they are a member of', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $other = Organization::factory()->create();

    $organization->members()->attach($user, ['role' => OrganizationRole::Member->value]);

    expect($user->belongsToOrganization($organization))->toBeTrue();
    expect($user->belongsToOrganization($other))->toBeFalse();
});

test('a users organization role is readable and owners are identified', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $organization = Organization::factory()->create();

    $organization->members()->attach($owner, ['role' => OrganizationRole::Owner->value]);
    $organization->members()->attach($member, ['role' => OrganizationRole::Member->value]);

    expect($owner->organizationRole($organization))->toBe(OrganizationRole::Owner);
    expect($owner->ownsOrganization($organization))->toBeTrue();
    expect($member->ownsOrganization($organization))->toBeFalse();
});

test('a non member has no role and no permissions', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    expect($user->organizationRole($organization))->toBeNull();
    expect($user->hasOrganizationPermission($organization, OrganizationPermission::UpdateOrganization))->toBeFalse();
});

test('client contacts are identified and hold no permissions', function () {
    $contact = User::factory()->create();
    $organization = Organization::factory()->create();

    $organization->members()->attach($contact, ['role' => OrganizationRole::Client->value]);

    expect($contact->isClientContact($organization))->toBeTrue();
    expect($contact->hasOrganizationPermission($organization, OrganizationPermission::UpdateOrganization))->toBeFalse();
    expect($contact->hasOrganizationPermission($organization, OrganizationPermission::CreateInvitation))->toBeFalse();
});

test('members are not client contacts', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();

    $organization->members()->attach($member, ['role' => OrganizationRole::Member->value]);

    expect($member->isClientContact($organization))->toBeFalse();
});

test('a user can list the organizations they belong to', function () {
    $user = User::factory()->create();
    $first = Organization::factory()->create();
    $second = Organization::factory()->create();
    Organization::factory()->create();

    $first->members()->attach($user, ['role' => OrganizationRole::Owner->value]);
    $second->members()->attach($user, ['role' => OrganizationRole::Client->value]);

    expect($user->organizations()->pluck('organizations.id')->sort()->values()->all())
        ->toBe(collect([$first->id, $second->id])->sort()->values()->all());
});
