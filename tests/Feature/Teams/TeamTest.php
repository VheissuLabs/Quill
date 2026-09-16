<?php

use App\Enums\IssueStatus;
use App\Enums\ProjectVisibility;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

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

test('the team edit page offers the other teams of the organization as merge targets', function () {
    $admin = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($admin, 'Admin');

    $team = Team::factory()->for($organization)->create(['name' => 'Design']);
    $sibling = Team::factory()->for($organization)->create(['name' => 'Engineering']);
    Team::factory()->for(Organization::factory())->create(['name' => 'Outsiders']);

    $response = $this
        ->actingAs($admin)
        ->get(route('teams.edit', ['current_organization' => $organization, 'team' => $team]));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('otherTeams', fn ($teams) => collect($teams)->pluck('name')->all() === ['Engineering'])
            ->etc(),
        );

    expect($sibling->organization_id)->toBe($organization->id);
});

test('merging a team moves its members, projects, sprints and issues to the target team', function () {
    $admin = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($admin, 'Admin');

    $source = Team::factory()->for($organization)->create(['name' => 'Design']);
    $target = Team::factory()->for($organization)->create(['name' => 'Engineering']);

    $mover = User::factory()->create();
    $organization->addMember($mover, 'Member');
    $source->members()->attach($mover);
    $mover->update(['current_team_id' => $source->id]);

    $project = Project::factory()->for($organization)->create(['visibility' => ProjectVisibility::Private]);
    $project->teams()->attach($source);

    $sprint = Sprint::factory()->for($source, 'owner')->create();
    $inProgress = $source->columns->firstWhere('status', IssueStatus::InProgress);
    $issue = Issue::factory()->for($project)->create([
        'team_id' => $source->id,
        'sprint_id' => $sprint->id,
        'board_column_id' => $inProgress->id,
        'status' => IssueStatus::InProgress,
    ]);

    $response = $this
        ->actingAs($admin)
        ->delete(route('teams.destroy', ['current_organization' => $organization, 'team' => $source]), [
            'target_team_id' => $target->id,
        ]);

    $response->assertRedirect(route('teams.index', ['current_organization' => $organization]));

    $this->assertModelMissing($source);

    expect($target->members()->whereKey($mover->id)->exists())->toBeTrue();
    expect($mover->fresh()->current_team_id)->toBe($target->id);
    expect($project->teams()->pluck('teams.id')->all())->toBe([$target->id]);
    expect($sprint->fresh()->owner->is($target))->toBeTrue();

    $issue->refresh();
    expect($issue->team_id)->toBe($target->id);
    expect($issue->status)->toBe(IssueStatus::InProgress);
    expect($issue->board_column_id)->toBe($target->columns->firstWhere('status', IssueStatus::InProgress)->id);
});

test('deleting a team with no destination hands its board back to the organization', function () {
    $admin = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($admin, 'Admin');

    $team = Team::factory()->for($organization)->create();

    $mover = User::factory()->create();
    $organization->addMember($mover, 'Member');
    $team->members()->attach($mover);
    $mover->update(['current_team_id' => $team->id]);

    $project = Project::factory()->for($organization)->create(['visibility' => ProjectVisibility::Private]);
    $project->teams()->attach($team);

    $sprint = Sprint::factory()->for($team, 'owner')->create();
    $inProgress = $team->columns->firstWhere('status', IssueStatus::InProgress);
    $issue = Issue::factory()->for($project)->create([
        'team_id' => $team->id,
        'sprint_id' => $sprint->id,
        'board_column_id' => $inProgress->id,
        'status' => IssueStatus::InProgress,
    ]);

    $this
        ->actingAs($admin)
        ->delete(route('teams.destroy', ['current_organization' => $organization, 'team' => $team]))
        ->assertRedirect(route('teams.index', ['current_organization' => $organization]));

    $this->assertModelMissing($team);

    expect($sprint->fresh()->owner->is($organization))->toBeTrue();
    expect($mover->fresh()->current_team_id)->toBeNull();
    expect($project->teams()->count())->toBe(0);

    $issue->refresh();
    expect($issue->team_id)->toBeNull();
    expect($issue->status)->toBe(IssueStatus::InProgress);
    expect($issue->boardColumn->owner->is($organization))->toBeTrue();
    expect($issue->boardColumn->status)->toBe(IssueStatus::InProgress);
});

test('merging returns work to the backlog when the target board has no column for its status', function () {
    $admin = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($admin, 'Admin');

    $source = Team::factory()->for($organization)->create();
    $target = Team::factory()->for($organization)->create();

    $target->columns()->where('status', '!=', IssueStatus::Todo->value)->delete();

    $project = Project::factory()->for($organization)->create();
    $inProgress = $source->columns->firstWhere('status', IssueStatus::InProgress);
    $issue = Issue::factory()->for($project)->create([
        'team_id' => $source->id,
        'board_column_id' => $inProgress->id,
        'status' => IssueStatus::InProgress,
    ]);

    $this
        ->actingAs($admin)
        ->delete(route('teams.destroy', ['current_organization' => $organization, 'team' => $source]), [
            'target_team_id' => $target->id,
        ])
        ->assertRedirect(route('teams.index', ['current_organization' => $organization]));

    $issue->refresh();
    expect($issue->team_id)->toBe($target->id);
    expect($issue->board_column_id)->toBeNull();
    expect($issue->status)->toBe(IssueStatus::Backlog);
});

test('merging records one activity entry on the target team', function () {
    $admin = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($admin, 'Admin');

    $source = Team::factory()->for($organization)->create(['name' => 'Design']);
    $target = Team::factory()->for($organization)->create();

    $project = Project::factory()->for($organization)->create();
    foreach (range(1, 3) as $number) {
        Issue::factory()->for($project)->create(['team_id' => $source->id, 'number' => $number]);
    }

    $this
        ->actingAs($admin)
        ->delete(route('teams.destroy', ['current_organization' => $organization, 'team' => $source]), [
            'target_team_id' => $target->id,
        ]);

    $activity = Activity::query()
        ->where('log_name', 'teams')
        ->where('event', 'merged')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->subject_id)->toBe($target->id);
    expect($activity->attribute_changes->get('attributes'))
        ->toMatchArray(['merged_from' => 'Design', 'issues_moved' => 3]);
});

test('a team cannot be merged into a team from another organization', function () {
    $admin = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($admin, 'Admin');

    $source = Team::factory()->for($organization)->create();
    $outsider = Team::factory()->for(Organization::factory())->create();

    $response = $this
        ->actingAs($admin)
        ->delete(route('teams.destroy', ['current_organization' => $organization, 'team' => $source]), [
            'target_team_id' => $outsider->id,
        ]);

    $response->assertSessionHasErrors('target_team_id');

    $this->assertModelExists($source);
    $this->assertModelExists($outsider);
});

test('a team cannot be merged into itself', function () {
    $admin = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($admin, 'Admin');

    $team = Team::factory()->for($organization)->create();

    $response = $this
        ->actingAs($admin)
        ->delete(route('teams.destroy', ['current_organization' => $organization, 'team' => $team]), [
            'target_team_id' => $team->id,
        ]);

    $response->assertSessionHasErrors('target_team_id');

    $this->assertModelExists($team);
});

test('members cannot merge a team', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');

    $source = Team::factory()->for($organization)->create();
    $target = Team::factory()->for($organization)->create();

    $response = $this
        ->actingAs($member)
        ->delete(route('teams.destroy', ['current_organization' => $organization, 'team' => $source]), [
            'target_team_id' => $target->id,
        ]);

    $response->assertForbidden();

    $this->assertModelExists($source);
});
