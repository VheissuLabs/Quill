<?php

use App\Models\Organization;
use App\Models\User;

function memberWithRole(Organization $organization, string $role): User
{
    $user = User::factory()->create();

    $organization->members()->attach($user);

    $user->assignOrganizationRole($organization, $role);

    return $user;
}

test('members can view their organization and outsiders cannot', function () {
    $organization = Organization::factory()->create();
    $member = memberWithRole($organization, 'member');
    $outsider = User::factory()->create();

    expect($member->can('view', $organization))->toBeTrue();
    expect($outsider->can('view', $organization))->toBeFalse();
});

test('owners and admins are granted organization:update', function () {
    $organization = Organization::factory()->create();

    expect(memberWithRole($organization, 'owner')->canInOrganization($organization, 'organization:update'))->toBeTrue();
    expect(memberWithRole($organization, 'admin')->canInOrganization($organization, 'organization:update'))->toBeTrue();
    expect(memberWithRole($organization, 'member')->canInOrganization($organization, 'organization:update'))->toBeFalse();
    expect(memberWithRole($organization, 'client')->canInOrganization($organization, 'organization:update'))->toBeFalse();
});

test('only owners are granted organization:delete', function () {
    $organization = Organization::factory()->create();

    expect(memberWithRole($organization, 'owner')->canInOrganization($organization, 'organization:delete'))->toBeTrue();
    expect(memberWithRole($organization, 'admin')->canInOrganization($organization, 'organization:delete'))->toBeFalse();
    expect(memberWithRole($organization, 'member')->canInOrganization($organization, 'organization:delete'))->toBeFalse();
});

test('client contacts can view but hold no member permissions', function () {
    $organization = Organization::factory()->create();
    $contact = memberWithRole($organization, 'client');

    expect($contact->can('view', $organization))->toBeTrue();

    foreach (['member:add', 'member:update', 'member:remove', 'invitation:create', 'invitation:cancel'] as $permission) {
        expect($contact->canInOrganization($organization, $permission))->toBeFalse();
    }
});

test('owners and admins are granted the member and invitation permissions', function () {
    $organization = Organization::factory()->create();

    foreach (['owner', 'admin'] as $role) {
        $user = memberWithRole($organization, $role);

        expect($user->canInOrganization($organization, 'member:add'))->toBeTrue();
        expect($user->canInOrganization($organization, 'member:remove'))->toBeTrue();
        expect($user->canInOrganization($organization, 'invitation:create'))->toBeTrue();
    }
});

test('an outsider is granted nothing and cannot view', function () {
    $organization = Organization::factory()->create();
    $outsider = User::factory()->create();

    expect($outsider->can('view', $organization))->toBeFalse();

    foreach (['organization:update', 'organization:delete', 'member:add', 'invitation:create'] as $permission) {
        expect($outsider->canInOrganization($organization, $permission))->toBeFalse();
    }
})->note('canInOrganization refuses a non-member before it ever reaches the permission tables.');
