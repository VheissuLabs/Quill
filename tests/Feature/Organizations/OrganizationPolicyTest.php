<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;

function memberWithRole(Organization $organization, OrganizationRole $role): User
{
    $user = User::factory()->create();

    $organization->members()->attach($user);

    $user->assignOrganizationRole($organization, $role->value);

    return $user;
}

test('members can view their organization and outsiders cannot', function () {
    $organization = Organization::factory()->create();
    $member = memberWithRole($organization, OrganizationRole::Member);
    $outsider = User::factory()->create();

    expect($member->can('view', $organization))->toBeTrue();
    expect($outsider->can('view', $organization))->toBeFalse();
});

test('owners and admins can update the organization', function () {
    $organization = Organization::factory()->create();

    expect(memberWithRole($organization, OrganizationRole::Owner)->can('update', $organization))->toBeTrue();
    expect(memberWithRole($organization, OrganizationRole::Admin)->can('update', $organization))->toBeTrue();
    expect(memberWithRole($organization, OrganizationRole::Member)->can('update', $organization))->toBeFalse();
    expect(memberWithRole($organization, OrganizationRole::Client)->can('update', $organization))->toBeFalse();
});

test('only owners can delete the organization', function () {
    $organization = Organization::factory()->create();

    expect(memberWithRole($organization, OrganizationRole::Owner)->can('delete', $organization))->toBeTrue();
    expect(memberWithRole($organization, OrganizationRole::Admin)->can('delete', $organization))->toBeFalse();
    expect(memberWithRole($organization, OrganizationRole::Member)->can('delete', $organization))->toBeFalse();
});

test('client contacts can view but cannot manage members', function () {
    $organization = Organization::factory()->create();
    $contact = memberWithRole($organization, OrganizationRole::Client);

    expect($contact->can('view', $organization))->toBeTrue();
    expect($contact->can('addMember', $organization))->toBeFalse();
    expect($contact->can('updateMember', $organization))->toBeFalse();
    expect($contact->can('removeMember', $organization))->toBeFalse();
    expect($contact->can('inviteMember', $organization))->toBeFalse();
    expect($contact->can('cancelInvitation', $organization))->toBeFalse();
});

test('owners and admins can manage members and invitations', function () {
    $organization = Organization::factory()->create();

    foreach ([OrganizationRole::Owner, OrganizationRole::Admin] as $role) {
        $user = memberWithRole($organization, $role);

        expect($user->can('addMember', $organization))->toBeTrue();
        expect($user->can('removeMember', $organization))->toBeTrue();
        expect($user->can('inviteMember', $organization))->toBeTrue();
    }
});

test('an outsider cannot do anything to an organization', function () {
    $organization = Organization::factory()->create();
    $outsider = User::factory()->create();

    expect($outsider->can('view', $organization))->toBeFalse();
    expect($outsider->can('update', $organization))->toBeFalse();
    expect($outsider->can('delete', $organization))->toBeFalse();
    expect($outsider->can('addMember', $organization))->toBeFalse();
    expect($outsider->can('inviteMember', $organization))->toBeFalse();
});
