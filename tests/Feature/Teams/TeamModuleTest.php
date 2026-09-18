<?php

use App\Enums\IssueStatus;
use App\Enums\OrganizationCapability;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('an admin can switch sprints off for a team', function () {
    $admin = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($admin, 'Admin');
    $team = Team::factory()->for($organization)->create();

    $this->actingAs($admin)
        ->patch(route('teams.modules.update', ['current_organization' => $organization, 'team' => $team]), [
            'sprints_enabled' => false,
        ])
        ->assertRedirect();

    expect($team->fresh()->canUse(OrganizationCapability::Sprints))->toBeFalse();
});

test('switching sprints back on restores them for that team', function () {
    $admin = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($admin, 'Admin');
    $team = Team::factory()->for($organization)->create();
    $team->updateModuleSettings(['sprints_enabled' => false]);

    $this->actingAs($admin)
        ->patch(route('teams.modules.update', ['current_organization' => $organization, 'team' => $team]), [
            'sprints_enabled' => true,
        ])
        ->assertRedirect();

    expect($team->fresh()->canUse(OrganizationCapability::Sprints))->toBeTrue();
});

test('switching sprints off for one team leaves the others alone', function () {
    $admin = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($admin, 'Admin');
    $team = Team::factory()->for($organization)->create();
    $other = Team::factory()->for($organization)->create();

    $this->actingAs($admin)
        ->patch(route('teams.modules.update', ['current_organization' => $organization, 'team' => $team]), [
            'sprints_enabled' => false,
        ]);

    expect($team->fresh()->canUse(OrganizationCapability::Sprints))->toBeFalse()
        ->and($other->fresh()->canUse(OrganizationCapability::Sprints))->toBeTrue();
});

test('a team with sprints off hides its sprint from its own members', function () {
    $admin = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($admin, 'Admin');
    $team = Team::factory()->for($organization)->create();
    $team->members()->attach($admin);
    $sprint = Sprint::factory()->for($team, 'owner')->create();

    $this->actingAs($admin)
        ->patch(route('teams.modules.update', ['current_organization' => $organization, 'team' => $team]), [
            'sprints_enabled' => false,
        ]);

    $this->actingAs($admin)
        ->get(route('sprints.show', ['current_organization' => $organization, 'sprint' => $sprint]))
        ->assertForbidden();
});

test('a member without permission cannot change a team module', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');
    $team = Team::factory()->for($organization)->create();

    $this->actingAs($member)
        ->patch(route('teams.modules.update', ['current_organization' => $organization, 'team' => $team]), [
            'sprints_enabled' => false,
        ])
        ->assertForbidden();

    expect($team->fresh()->canUse(OrganizationCapability::Sprints))->toBeTrue();
});

test('the team settings page carries the module state', function () {
    $admin = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($admin, 'Admin');
    $team = Team::factory()->for($organization)->create();
    $team->updateModuleSettings(['sprints_enabled' => false]);

    $this->actingAs($admin)
        ->get(route('teams.edit', ['current_organization' => $organization, 'team' => $team]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('modules.sprints', false)
            ->where('organizationModules.sprints', true));
});

test('a team with sprints off keeps them off every list that shows them', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');
    $team = Team::factory()->for($organization)->create();
    $team->members()->attach($member);
    $project = Project::factory()->for($organization)->create();
    $sprint = Sprint::factory()->for($team, 'owner')->create(['name' => 'Hidden sprint']);

    $team->updateModuleSettings(['sprints_enabled' => false]);

    $this->actingAs($member)
        ->get(route('issues.index', ['current_organization' => $organization]))
        ->assertInertia(fn (Assert $page) => $page->has('sprints', 0));

    $this->actingAs($member)
        ->get(route('sprints.index', ['current_organization' => $organization]))
        ->assertInertia(fn (Assert $page) => $page->has('sprints', 0));

    $palette = $this->actingAs($member)
        ->getJson(route('palette.search', ['current_organization' => $organization, 'q' => 'hidden']));

    expect(collect($palette->json())->pluck('type'))->not->toContain('sprint');

    $issue = Issue::factory()->for($project)->create();

    $this->actingAs($member)
        ->post(route('issues.comments.store', ['current_organization' => $organization, 'issue' => $issue]), [
            'body' => '/sprint Hidden sprint',
        ])
        ->assertSessionHasErrors('body');

    expect($issue->fresh()->sprint_id)->toBeNull();
});

test('a team that still runs sprints keeps seeing them', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');
    $team = Team::factory()->for($organization)->create();
    $team->members()->attach($member);
    Sprint::factory()->for($team, 'owner')->create(['name' => 'Visible sprint']);

    $this->actingAs($member)
        ->get(route('issues.index', ['current_organization' => $organization]))
        ->assertInertia(fn (Assert $page) => $page->has('sprints', 1));
});

test('switching sprints off returns planned work to the backlog', function () {
    $admin = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($admin, 'Admin');
    $team = Team::factory()->for($organization)->create();
    $team->members()->attach($admin);
    $project = Project::factory()->for($organization)->create();
    $sprint = Sprint::factory()->for($team, 'owner')->active()->create();
    $planned = Issue::factory()->for($project)->for($sprint)->create(['status' => IssueStatus::Todo]);
    $done = Issue::factory()->for($project)->for($sprint)->create(['status' => IssueStatus::Done]);

    $this->actingAs($admin)
        ->patch(route('teams.modules.update', ['current_organization' => $organization, 'team' => $team]), [
            'sprints_enabled' => false,
        ])
        ->assertRedirect();

    expect($planned->fresh())
        ->sprint_id->toBeNull()
        ->board_column_id->toBeNull()
        ->status->toBe(IssueStatus::Backlog);

    expect($done->fresh())
        ->sprint_id->toBeNull()
        ->status->toBe(IssueStatus::Done);
});

test('the issue board hides the sprint column when the team does not run sprints', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');
    $team = Team::factory()->for($organization)->create();
    $team->members()->attach($member);

    $this->actingAs($member)
        ->get(route('issues.index', ['current_organization' => $organization]))
        ->assertInertia(fn (Assert $page) => $page->where('sprintsEnabled', true));

    $team->updateModuleSettings(['sprints_enabled' => false]);

    $this->actingAs($member)
        ->get(route('issues.index', ['current_organization' => $organization]))
        ->assertInertia(fn (Assert $page) => $page->where('sprintsEnabled', false));
});

test('the shared current team says whether it runs sprints', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');
    $team = Team::factory()->for($organization)->create();
    $team->members()->attach($member);

    $this->actingAs($member)
        ->get(route('issues.index', ['current_organization' => $organization]))
        ->assertInertia(fn (Assert $page) => $page->where('currentTeam.runsSprints', true));

    $team->updateModuleSettings(['sprints_enabled' => false]);

    $this->actingAs($member)
        ->get(route('issues.index', ['current_organization' => $organization]))
        ->assertInertia(fn (Assert $page) => $page->where('currentTeam.runsSprints', false));
});
