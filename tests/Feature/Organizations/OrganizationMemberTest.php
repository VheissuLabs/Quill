<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;

test('organization member roles can be updated by owners', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $organization = Organization::factory()->create();

    $organization->addMember($owner, OrganizationRole::Owner);
    $organization->addMember($member, OrganizationRole::Member);

    $response = $this
        ->actingAs($owner)
        ->patch(route('organizations.members.update', [$organization, $member]), [
            'role' => OrganizationRole::Admin->value,
        ]);

    $response->assertRedirect(route('organizations.edit', $organization));

    expect($member->fresh()->organizationRole($organization))->toBe(OrganizationRole::Admin);
});

test('organization member roles cannot be updated by non owners', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $organization = Organization::factory()->create();

    $organization->addMember($owner, OrganizationRole::Owner);
    $organization->addMember($admin, OrganizationRole::Admin);
    $organization->addMember($member, OrganizationRole::Member);

    $response = $this
        ->actingAs($admin)
        ->patch(route('organizations.members.update', [$organization, $member]), [
            'role' => OrganizationRole::Admin->value,
        ]);

    $response->assertForbidden();
});

test('organization members can be removed by owners', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $organization = Organization::factory()->create();

    $organization->addMember($owner, OrganizationRole::Owner);
    $organization->addMember($member, OrganizationRole::Member);

    $response = $this
        ->actingAs($owner)
        ->delete(route('organizations.members.destroy', [$organization, $member]));

    $response->assertRedirect(route('organizations.edit', $organization));

    expect($member->fresh()->belongsToOrganization($organization))->toBeFalse();
});

test('organization members cannot be removed by non owners', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $organization = Organization::factory()->create();

    $organization->addMember($owner, OrganizationRole::Owner);
    $organization->addMember($admin, OrganizationRole::Admin);
    $organization->addMember($member, OrganizationRole::Member);

    $response = $this
        ->actingAs($admin)
        ->delete(route('organizations.members.destroy', [$organization, $member]));

    $response->assertForbidden();
});

test('organization owner cannot be removed', function () {
    $owner = User::factory()->create();
    $organization = Organization::factory()->create();

    $organization->addMember($owner, OrganizationRole::Owner);

    $response = $this
        ->actingAs($owner)
        ->delete(route('organizations.members.destroy', [$organization, $owner]));

    $response->assertForbidden();

    expect($owner->fresh()->belongsToOrganization($organization))->toBeTrue();
});

test('organization member role cannot be set to owner', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $organization = Organization::factory()->create();

    $organization->addMember($owner, OrganizationRole::Owner);
    $organization->addMember($member, OrganizationRole::Member);

    $response = $this
        ->actingAs($owner)
        ->patch(route('organizations.members.update', [$organization, $member]), [
            'role' => OrganizationRole::Owner->value,
        ]);

    $response->assertSessionHasErrors('role');

    expect($member->fresh()->organizationRole($organization))->toBe(OrganizationRole::Member);
});

test('removed member falls back to another organization they belong to', function () {
    $owner = User::factory()->create();
    $member = User::factory()->withOrganization()->create();
    $otherOrganization = $member->currentOrganization;
    $organization = Organization::factory()->create();

    $organization->addMember($owner, OrganizationRole::Owner);
    $organization->addMember($member, OrganizationRole::Member);

    $member->update(['current_organization_id' => $organization->id]);

    $this
        ->actingAs($owner)
        ->delete(route('organizations.members.destroy', [$organization, $member]));

    expect($member->fresh()->current_organization_id)->toEqual($otherOrganization->id);
});
