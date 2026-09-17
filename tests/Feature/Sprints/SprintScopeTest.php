<?php

use App\Enums\OrganizationCapability;
use App\Models\Organization;
use App\Models\Sprint;
use App\Models\Team;
use App\Models\TeamModuleSetting;
use App\Models\User;

test('a member cannot open a sprint owned by a team they are not on', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');
    $sprint = Sprint::factory()->for(Team::factory()->for($organization), 'owner')->create();

    $this->actingAs($member)
        ->get(route('sprints.show', ['current_organization' => $organization, 'sprint' => $sprint]))
        ->assertForbidden();
});

test('a member can open a sprint owned by their own team', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');
    $team = Team::factory()->for($organization)->create();
    $team->members()->attach($member);
    $sprint = Sprint::factory()->for($team, 'owner')->create();

    $this->actingAs($member)
        ->get(route('sprints.show', ['current_organization' => $organization, 'sprint' => $sprint]))
        ->assertOk();
});

test('an admin can still administer a team sprint they are not on', function () {
    $admin = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($admin, 'Admin');
    $sprint = Sprint::factory()->for(Team::factory()->for($organization), 'owner')->create();

    $this->actingAs($admin)
        ->get(route('sprints.show', ['current_organization' => $organization, 'sprint' => $sprint]))
        ->assertOk();
});

test('an organization owned sprint stays open to every member', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');
    $sprint = Sprint::factory()->for($organization, 'owner')->create();

    $this->actingAs($member)
        ->get(route('sprints.show', ['current_organization' => $organization, 'sprint' => $sprint]))
        ->assertOk();
});

test('a team can switch sprints off for itself', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');
    $team = Team::factory()->for($organization)->create();
    $team->members()->attach($member);
    $sprint = Sprint::factory()->for($team, 'owner')->create();

    expect($team->canUse(OrganizationCapability::Sprints))->toBeTrue();

    TeamModuleSetting::create([
        'team_id' => $team->id,
        'module' => 'development',
        'capability' => 'sprints',
        'enabled' => false,
    ]);

    expect($team->fresh()->canUse(OrganizationCapability::Sprints))->toBeFalse();

    $this->actingAs($member)
        ->get(route('sprints.show', ['current_organization' => $organization, 'sprint' => $sprint]))
        ->assertForbidden();
});

test('a team cannot switch sprints on when the organization has them off', function () {
    $organization = Organization::factory()->create();
    $organization->capabilitySettings()->create([
        'module' => 'development',
        'capability' => 'sprints',
        'enabled' => false,
    ]);
    $team = Team::factory()->for($organization)->create();
    TeamModuleSetting::create([
        'team_id' => $team->id,
        'module' => 'development',
        'capability' => 'sprints',
        'enabled' => true,
    ]);

    expect($team->canUse(OrganizationCapability::Sprints))->toBeFalse();
});
