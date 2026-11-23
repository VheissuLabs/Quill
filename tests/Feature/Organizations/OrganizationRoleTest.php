<?php

use App\Enums\OrganizationPermission;
use App\Models\Organization;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('owners can define a role with permissions and it becomes grantable', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($owner, 'Owner');
    $organization->addMember($member, 'Member');

    $this
        ->actingAs($owner)
        ->post(route('organizations.roles.store', $organization), [
            'name' => 'Product manager',
            'permissions' => [OrganizationPermission::ManageProjects->value, OrganizationPermission::ManageFeedback->value],
        ])
        ->assertRedirect(route('roles.index', ['current_organization' => $organization]));

    $role = $organization->role('Product manager');
    expect($role)->not->toBeNull();
    expect($role->permissions->pluck('name')->sort()->values()->all())->toBe(['feedback:manage', 'project:manage']);

    $organization->changeMemberRole($member, $role);

    expect($member->fresh()->toOrganizationPermissions($organization))
        ->toMatchArray(['canManageProjects' => true, 'canManageFeedback' => true, 'canManageIssues' => false]);

    $this
        ->actingAs($owner)
        ->get(route('roles.index', ['current_organization' => $organization]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('roles/Index')
            ->where('roles', fn ($roles) => collect($roles)->pluck('name')->all() === ['Owner', 'Admin', 'Member', 'Product manager'])
            ->where('roles.0.isOwner', true)
            ->where('roles.3.membersCount', 1)
            ->where('roles.3.members.0.name', $member->name)
            ->where('permissions.canManageRoles', true),
        );

    $this
        ->actingAs($owner)
        ->get(route('organizations.edit', $organization))
        ->assertInertia(fn (Assert $page) => $page
            ->where('availableRoles', fn ($roles) => collect($roles)->pluck('label')->contains('Product manager')),
        );
});

test('role names are unique within an organization but free across organizations', function () {
    $owner = User::factory()->create();
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $organization->addMember($owner, 'Owner');
    $otherOrganization->addMember($owner, 'Owner');

    $this
        ->actingAs($owner)
        ->post(route('organizations.roles.store', $organization), ['name' => 'Admin', 'permissions' => []])
        ->assertSessionHasErrors(['name' => 'This organization already has a role with that name.']);

    $this
        ->actingAs($owner)
        ->post(route('organizations.roles.store', $otherOrganization), ['name' => 'CEO', 'permissions' => []])
        ->assertRedirect();
    $this
        ->actingAs($owner)
        ->post(route('organizations.roles.store', $organization), ['name' => 'CEO', 'permissions' => []])
        ->assertRedirect();

    expect($organization->role('CEO'))->not->toBeNull();
    expect($otherOrganization->role('CEO'))->not->toBeNull();
});

test('roles can be renamed and have their permissions changed', function () {
    $owner = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($owner, 'Owner');
    $role = $organization->role('Admin');

    $this
        ->actingAs($owner)
        ->patch(route('organizations.roles.update', [$organization, $role]), [
            'name' => 'Team lead',
            'permissions' => [OrganizationPermission::ManageSprints->value],
        ])
        ->assertRedirect(route('roles.index', ['current_organization' => $organization]));

    expect($role->fresh())
        ->name->toBe('Team lead')
        ->permissions->pluck('name')->all()->toBe(['sprint:manage']);
});

test('the owner role cannot be changed or deleted', function () {
    $owner = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($owner, 'Owner');
    $ownerRole = $organization->ownerRole();

    $this
        ->actingAs($owner)
        ->patch(route('organizations.roles.update', [$organization, $ownerRole]), ['name' => 'Boss', 'permissions' => []])
        ->assertForbidden();
    $this
        ->actingAs($owner)
        ->delete(route('organizations.roles.destroy', [$organization, $ownerRole]))
        ->assertForbidden();

    expect($ownerRole->fresh())->name->toBe('Owner')->permissions->toHaveCount(count(OrganizationPermission::cases()));
});

test('a role somebody holds cannot be deleted, an unused one can', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($owner, 'Owner');
    $organization->addMember($member, 'Member');

    $this
        ->actingAs($owner)
        ->delete(route('organizations.roles.destroy', [$organization, $organization->role('Member')]))
        ->assertStatus(409);
    $this
        ->actingAs($owner)
        ->delete(route('organizations.roles.destroy', [$organization, $organization->role('Admin')]))
        ->assertRedirect(route('roles.index', ['current_organization' => $organization]));

    expect($organization->roles()->pluck('name')->sort()->values()->all())->toBe(['Member', 'Owner']);
});

test('a role page shows who holds it, who could be given it without leaving the organization ownerless, and lets an admin hand it out', function () {
    $owner = User::factory()->create(['name' => 'Olive Owner']);
    $member = User::factory()->create(['name' => 'Mia Member']);
    $organization = Organization::factory()->create();
    $organization->addMember($owner, 'Owner');
    $organization->addMember($member, 'Member');
    $admin = $organization->role('Admin');

    $this
        ->actingAs($owner)
        ->get(route('roles.show', ['current_organization' => $organization, 'role' => $admin]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('roles/Show')
            ->where('role.name', 'Admin')
            ->where('role.members', [])
            ->where('candidates', fn ($candidates) => collect($candidates)->pluck('name')->all() === ['Mia Member'])
            ->where('permissions.canUpdateMember', true),
        );

    $this
        ->actingAs($owner)
        ->from(route('roles.show', ['current_organization' => $organization, 'role' => $admin]))
        ->patch(route('organizations.members.update', [$organization, $member]), ['role_id' => $admin->id])
        ->assertRedirect(route('roles.show', ['current_organization' => $organization, 'role' => $admin]));

    $this
        ->actingAs($owner)
        ->get(route('roles.show', ['current_organization' => $organization, 'role' => $admin]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('role.members.0.name', 'Mia Member')
            ->where('candidates', []),
        );

    $this
        ->actingAs($member)
        ->get(route('roles.show', ['current_organization' => $organization, 'role' => $admin]))
        ->assertOk();

    $this
        ->actingAs(User::factory()->create())
        ->get(route('roles.show', ['current_organization' => $organization, 'role' => $admin]))
        ->assertForbidden();
});

test('roles of another organization cannot be reached', function () {
    $owner = User::factory()->create();
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $organization->addMember($owner, 'Owner');

    $this
        ->actingAs($owner)
        ->patch(route('organizations.roles.update', [$organization, $otherOrganization->role('Admin')]), ['name' => 'Hijacked', 'permissions' => []])
        ->assertNotFound();

    expect($otherOrganization->role('Admin'))->not->toBeNull();
});

test('managing roles requires the manage roles permission', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($owner, 'Owner');
    $organization->addMember($admin, 'Admin');

    $this
        ->actingAs($admin)
        ->post(route('organizations.roles.store', $organization), ['name' => 'Sneaky', 'permissions' => []])
        ->assertForbidden();

    $this
        ->actingAs($admin)
        ->get(route('roles.index', ['current_organization' => $organization]))
        ->assertInertia(fn (Assert $page) => $page->where('permissions.canManageRoles', false));

    $this
        ->actingAs(User::factory()->create())
        ->get(route('roles.index', ['current_organization' => $organization]))
        ->assertForbidden();
});

test('the owner role page lets an owner step another owner down but never the last one', function () {
    $karl = User::factory()->create(['name' => 'Karl']);
    $kacey = User::factory()->create(['name' => 'Kacey']);
    $organization = Organization::factory()->create();
    $organization->addMember($karl, 'Owner');
    $owner = $organization->ownerRole();

    $this->actingAs($karl)
        ->get(route('roles.show', ['current_organization' => $organization, 'role' => $owner]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('members', 1)
            ->where('members.0.canUpdate', false)
            ->where('members.0.canRemove', false));

    $organization->addMember($kacey, $owner);

    $this->actingAs($karl)
        ->get(route('roles.show', ['current_organization' => $organization, 'role' => $owner]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('members', 2)
            ->where('members.0.name', 'Kacey')
            ->where('members.0.canUpdate', true)
            ->where('members.0.canRemove', true));
});
