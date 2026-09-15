<?php

use App\Models\Organization;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('teams can be created by admins', function () {
    $admin = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($admin, 'Admin');

    $response = $this
        ->actingAs($admin)
        ->post(route('teams.store', ['current_organization' => $organization]), [
            'name' => 'Marketing',
        ]);

    $team = Team::firstWhere('name', 'Marketing');

    $response->assertRedirect(route('teams.edit', ['current_organization' => $organization, 'team' => $team]));

    expect($team->organization_id)->toBe($organization->id);
});

test('teams cannot be created by members', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');

    $response = $this
        ->actingAs($member)
        ->post(route('teams.store', ['current_organization' => $organization]), [
            'name' => 'Marketing',
        ]);

    $response->assertForbidden();
});

test('the team edit page lists members and organization members who can be added', function () {
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($admin, 'Admin');
    $organization->addMember($member, 'Member');
    $team = Team::factory()->for($organization)->create();
    $team->members()->attach($member);

    $response = $this
        ->actingAs($admin)
        ->get(route('teams.edit', ['current_organization' => $organization, 'team' => $team]));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('teams/Edit')
            ->has('members', 1)
            ->where('members.0.id', $member->id)
            ->has('availableMembers', 1)
            ->where('availableMembers.0.id', $admin->id),
        );
});

test('organization members can be added to a team', function () {
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($admin, 'Admin');
    $organization->addMember($member, 'Member');
    $team = Team::factory()->for($organization)->create();

    $response = $this
        ->actingAs($admin)
        ->post(route('teams.members.store', ['current_organization' => $organization, 'team' => $team]), [
            'user_id' => $member->id,
        ]);

    $response->assertRedirect();

    expect($team->members->pluck('id')->all())->toBe([$member->id]);
});

test('users outside the organization cannot be added to a team', function () {
    $admin = User::factory()->create();
    $outsider = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($admin, 'Admin');
    $team = Team::factory()->for($organization)->create();

    $response = $this
        ->actingAs($admin)
        ->post(route('teams.members.store', ['current_organization' => $organization, 'team' => $team]), [
            'user_id' => $outsider->id,
        ]);

    $response->assertSessionHasErrors(['user_id' => 'Only organization members can be added to a team.']);

    expect($team->members)->toBeEmpty();
});

test('team members can be removed', function () {
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($admin, 'Admin');
    $organization->addMember($member, 'Member');
    $team = Team::factory()->for($organization)->create();
    $team->members()->attach($member);

    $response = $this
        ->actingAs($admin)
        ->delete(route('teams.members.destroy', ['current_organization' => $organization, 'team' => $team, 'member' => $member]));

    $response->assertRedirect();

    expect($team->members()->count())->toBe(0);
});

test('teams can be deleted by admins', function () {
    $admin = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($admin, 'Admin');
    $team = Team::factory()->for($organization)->create();

    $response = $this
        ->actingAs($admin)
        ->delete(route('teams.destroy', ['current_organization' => $organization, 'team' => $team]));

    $response->assertRedirect(route('teams.index', ['current_organization' => $organization]));

    $this->assertModelMissing($team);
});
