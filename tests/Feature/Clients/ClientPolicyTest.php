<?php

use App\Models\Client;
use App\Models\Organization;
use App\Models\User;

function orgMember(Organization $organization, string $role): User
{
    $user = User::factory()->create();

    $organization->members()->attach($user);

    $user->assignOrganizationRole($organization, $role);

    return $user;
}

test('members of the organization can view its clients', function () {
    $organization = Organization::factory()->create();
    $client = Client::factory()->for($organization)->create();

    expect(orgMember($organization, 'member')->can('view', $client))->toBeTrue();
    expect(orgMember($organization, 'owner')->can('view', $client))->toBeTrue();
});

test('a user from another organization cannot see or change a client', function () {
    $organization = Organization::factory()->create();
    $other = Organization::factory()->create();
    $client = Client::factory()->for($organization)->create();

    $outsider = orgMember($other, 'owner');

    expect($outsider->can('view', $client))->toBeFalse();
    expect($outsider->canInOrganization($organization, 'client:update'))->toBeFalse();
    expect($outsider->canInOrganization($organization, 'client:delete'))->toBeFalse();
})->note('An owner elsewhere holds client:update in their own organization, never in this one.');

test('a user belonging to no organization cannot see a client', function () {
    $client = Client::factory()->create();

    expect(User::factory()->create()->can('view', $client))->toBeFalse();
});

test('owners and admins are granted client:create and client:update', function () {
    $organization = Organization::factory()->create();

    foreach (['owner', 'admin'] as $role) {
        $user = orgMember($organization, $role);

        expect($user->canInOrganization($organization, 'client:create'))->toBeTrue();
        expect($user->canInOrganization($organization, 'client:update'))->toBeTrue();
    }
});

test('members and client contacts are granted neither', function () {
    $organization = Organization::factory()->create();

    foreach (['member', 'client'] as $role) {
        $user = orgMember($organization, $role);

        expect($user->canInOrganization($organization, 'client:update'))->toBeFalse();
        expect($user->canInOrganization($organization, 'client:delete'))->toBeFalse();
    }
});

test('only owners are granted client:delete', function () {
    $organization = Organization::factory()->create();

    expect(orgMember($organization, 'owner')->canInOrganization($organization, 'client:delete'))->toBeTrue();
    expect(orgMember($organization, 'admin')->canInOrganization($organization, 'client:delete'))->toBeFalse();
});
