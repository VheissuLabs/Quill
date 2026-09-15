<?php

use App\Enums\OrganizationPermission;
use App\Models\Organization;
use App\Models\User;

test('organization member roles can be updated by owners', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $organization = Organization::factory()->create();

    $organization->addMember($owner, 'Owner');
    $organization->addMember($member, 'Member');

    $response = $this
        ->actingAs($owner)
        ->from(route('organizations.edit', $organization))
        ->patch(route('organizations.members.update', [$organization, $member]), [
            'role_id' => $organization->role('Admin')->id,
        ]);

    $response->assertRedirect(route('organizations.edit', $organization));

    expect($member->fresh()->organizationRole($organization)->name)->toBe('Admin');
});

test('organization member roles cannot be updated by non owners', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $organization = Organization::factory()->create();

    $organization->addMember($owner, 'Owner');
    $organization->addMember($admin, 'Admin');
    $organization->addMember($member, 'Member');

    $response = $this
        ->actingAs($admin)
        ->from(route('organizations.edit', $organization))
        ->patch(route('organizations.members.update', [$organization, $member]), [
            'role_id' => $organization->role('Admin')->id,
        ]);

    $response->assertForbidden();
});

test('organization members can be removed by owners', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $organization = Organization::factory()->create();

    $organization->addMember($owner, 'Owner');
    $organization->addMember($member, 'Member');

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

    $organization->addMember($owner, 'Owner');
    $organization->addMember($admin, 'Admin');
    $organization->addMember($member, 'Member');

    $response = $this
        ->actingAs($admin)
        ->delete(route('organizations.members.destroy', [$organization, $member]));

    $response->assertForbidden();
});

test('the last owner cannot be removed', function () {
    $owner = User::factory()->create();
    $organization = Organization::factory()->create();

    $organization->addMember($owner, 'Owner');

    $response = $this
        ->actingAs($owner)
        ->delete(route('organizations.members.destroy', [$organization, $owner]));

    $response->assertForbidden();

    expect($owner->fresh()->belongsToOrganization($organization))->toBeTrue();
});

test('an owner can demote or remove another owner, but admins with member permissions cannot', function () {
    $owner = User::factory()->create();
    $secondOwner = User::factory()->create();
    $thirdOwner = User::factory()->create();
    $admin = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($owner, 'Owner');
    $organization->addMember($secondOwner, 'Owner');
    $organization->addMember($thirdOwner, 'Owner');
    $organization->role('Admin')->givePermissionTo([OrganizationPermission::UpdateMember->value, OrganizationPermission::RemoveMember->value]);
    $organization->addMember($admin, 'Admin');

    $this
        ->actingAs($admin)
        ->delete(route('organizations.members.destroy', [$organization, $secondOwner]))
        ->assertForbidden();
    $this
        ->actingAs($admin)
        ->patch(route('organizations.members.update', [$organization, $secondOwner]), ['role_id' => $organization->role('Member')->id])
        ->assertForbidden();

    $this
        ->actingAs($owner)
        ->from(route('organizations.edit', $organization))
        ->patch(route('organizations.members.update', [$organization, $secondOwner]), ['role_id' => $organization->role('Member')->id])
        ->assertRedirect(route('organizations.edit', $organization));

    $this
        ->actingAs($owner)
        ->delete(route('organizations.members.destroy', [$organization, $thirdOwner]))
        ->assertRedirect();

    expect($secondOwner->fresh()->organizationRole($organization)->name)->toBe('Member');
    expect($organization->owners()->pluck('id')->all())->toBe([$owner->id]);
});

test('an owner can step down themselves while another owner remains', function () {
    $owner = User::factory()->create();
    $secondOwner = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($owner, 'Owner');
    $organization->addMember($secondOwner, 'Owner');

    $this
        ->actingAs($secondOwner)
        ->from(route('organizations.edit', $organization))
        ->patch(route('organizations.members.update', [$organization, $secondOwner]), [
            'role_id' => $organization->role('Member')->id,
        ])
        ->assertRedirect(route('organizations.edit', $organization));

    expect($secondOwner->fresh()->organizationRole($organization)->name)->toBe('Member');
    expect($organization->owners()->pluck('id')->all())->toBe([$owner->id]);
});

test('owners can make another member an owner', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($owner, 'Owner');
    $organization->addMember($member, 'Member');

    $this
        ->actingAs($owner)
        ->from(route('organizations.edit', $organization))
        ->patch(route('organizations.members.update', [$organization, $member]), [
            'role_id' => $organization->ownerRole()->id,
        ])
        ->assertRedirect(route('organizations.edit', $organization));

    expect($organization->isOwner($member->fresh()))->toBeTrue();
});

test('the member permission alone does not allow granting the owner role', function () {
    $owner = User::factory()->create();
    $manager = User::factory()->create();
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->roles()->create(['name' => 'People', 'guard_name' => 'web'])->syncPermissions([OrganizationPermission::UpdateMember->value]);
    $organization->addMember($owner, 'Owner');
    $organization->addMember($manager, 'People');
    $organization->addMember($member, 'Member');

    $this
        ->actingAs($manager)
        ->from(route('organizations.edit', $organization))
        ->patch(route('organizations.members.update', [$organization, $member]), [
            'role_id' => $organization->ownerRole()->id,
        ])
        ->assertForbidden();

    expect($member->fresh()->organizationRole($organization)->name)->toBe('Member');
});

test('the last owner cannot be demoted', function () {
    $owner = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($owner, 'Owner');

    $this
        ->actingAs($owner)
        ->from(route('organizations.edit', $organization))
        ->patch(route('organizations.members.update', [$organization, $owner]), [
            'role_id' => $organization->role('Member')->id,
        ])
        ->assertForbidden();

    expect($organization->isOwner($owner->fresh()))->toBeTrue();
});

test('removed member falls back to another organization they belong to', function () {
    $owner = User::factory()->create();
    $member = User::factory()->withOrganization()->create();
    $otherOrganization = $member->currentOrganization;
    $organization = Organization::factory()->create();

    $organization->addMember($owner, 'Owner');
    $organization->addMember($member, 'Member');

    $member->update(['current_organization_id' => $organization->id]);

    $this
        ->actingAs($owner)
        ->delete(route('organizations.members.destroy', [$organization, $member]));

    expect($member->fresh()->current_organization_id)->toEqual($otherOrganization->id);
});

test('a role belonging to another organization cannot be assigned', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $organization->addMember($owner, 'Owner');
    $organization->addMember($member, 'Member');

    $response = $this
        ->actingAs($owner)
        ->from(route('organizations.edit', $organization))
        ->patch(route('organizations.members.update', [$organization, $member]), [
            'role_id' => $otherOrganization->role('Admin')->id,
        ]);

    $response->assertSessionHasErrors('role_id');

    expect($member->fresh()->organizationRole($organization)->name)->toBe('Member');
});
