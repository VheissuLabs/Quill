<?php

use App\Enums\OrganizationRole;
use App\Models\Client;
use App\Models\Organization;
use App\Models\User;

function orgMember(Organization $organization, OrganizationRole $role): User
{
    $user = User::factory()->create();

    $organization->members()->attach($user, ['role' => $role->value]);

    return $user;
}

test('members of the organization can view its clients', function () {
    $organization = Organization::factory()->create();
    $client = Client::factory()->for($organization)->create();

    expect(orgMember($organization, OrganizationRole::Member)->can('view', $client))->toBeTrue();
    expect(orgMember($organization, OrganizationRole::Owner)->can('view', $client))->toBeTrue();
});

test('a user from another organization cannot see a client', function () {
    $organization = Organization::factory()->create();
    $other = Organization::factory()->create();
    $client = Client::factory()->for($organization)->create();

    $outsider = orgMember($other, OrganizationRole::Owner);

    expect($outsider->can('view', $client))->toBeFalse();
    expect($outsider->can('update', $client))->toBeFalse();
    expect($outsider->can('delete', $client))->toBeFalse();
});

test('a user belonging to no organization cannot see a client', function () {
    $client = Client::factory()->create();

    expect(User::factory()->create()->can('view', $client))->toBeFalse();
});

test('owners and admins can create and update clients', function () {
    $organization = Organization::factory()->create();
    $client = Client::factory()->for($organization)->create();

    foreach ([OrganizationRole::Owner, OrganizationRole::Admin] as $role) {
        $user = orgMember($organization, $role);

        expect($user->can('update', $client))->toBeTrue();
    }
});

test('members and client contacts cannot create or update clients', function () {
    $organization = Organization::factory()->create();
    $client = Client::factory()->for($organization)->create();

    foreach ([OrganizationRole::Member, OrganizationRole::Client] as $role) {
        $user = orgMember($organization, $role);

        expect($user->can('update', $client))->toBeFalse();
        expect($user->can('delete', $client))->toBeFalse();
    }
});

test('only owners can delete a client', function () {
    $organization = Organization::factory()->create();
    $client = Client::factory()->for($organization)->create();

    expect(orgMember($organization, OrganizationRole::Owner)->can('delete', $client))->toBeTrue();
    expect(orgMember($organization, OrganizationRole::Admin)->can('delete', $client))->toBeFalse();
});
