<?php

use App\Enums\IssueStatus;
use App\Enums\SprintStatus;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('sprints can be created by admins for one of the organization teams', function () {
    $admin = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($admin, 'Admin');
    $team = Team::factory()->for($organization)->create();
    $team->members()->attach($admin);

    $response = $this
        ->actingAs($admin)
        ->post(route('sprints.store', ['current_organization' => $organization]), [
            'name' => 'Sprint 1',
            'starts_at' => '2026-09-14',
            'ends_at' => '2026-09-25',
        ]);

    $sprint = Sprint::firstWhere('name', 'Sprint 1');

    $response->assertRedirect(route('sprints.show', ['current_organization' => $organization, 'sprint' => $sprint]));

    expect($sprint->owner->is($team))->toBeTrue();
    expect($sprint->status)->toBe(SprintStatus::Planned);
});

test('an organization with no teams runs its own sprints', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create();
    $organization->addMember($admin, 'Admin');

    $this->actingAs($admin)
        ->post(route('sprints.store', ['current_organization' => $organization]), [
            'name' => 'Sprint 1',
            'starts_at' => '2026-09-14',
            'ends_at' => '2026-09-25',
        ])
        ->assertRedirect();

    $sprint = Sprint::firstWhere('name', 'Sprint 1');

    expect($sprint->owner->is($organization))->toBeTrue();
    expect($sprint->teamId())->toBeNull();
});

test('sprints cannot be created by members', function () {
    $organization = Organization::factory()->create();
    $member = User::factory()->create();
    $organization->addMember($member, 'Member');
    Team::factory()->for($organization)->create();

    $this->actingAs($member)
        ->post(route('sprints.store', ['current_organization' => $organization]), [
            'name' => 'Sprint 1',
            'starts_at' => '2026-09-14',
            'ends_at' => '2026-09-25',
        ])
        ->assertForbidden();
});

test('the sprints index shows the current team only', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');
    $development = Team::factory()->for($organization)->create(['name' => 'Development']);
    $marketing = Team::factory()->for($organization)->create(['name' => 'Marketing']);
    Sprint::factory()->for($development, 'owner')->create(['name' => 'Dev sprint']);
    Sprint::factory()->for($marketing, 'owner')->create(['name' => 'Marketing sprint']);
    $member->switchOrganization($organization);
    $member->switchTeam($marketing);

    $this->actingAs($member)
        ->get(route('sprints.index', ['current_organization' => $organization]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('sprints', 1)
            ->where('sprints.0.name', 'Marketing sprint'),
        );
});

test('sprints cannot be viewed when the sprints module is disabled', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->updateModuleSettings([
        'development_enabled' => false,
        'development_sprints_enabled' => true,
    ]);
    $organization->addMember($member, 'Member');

    $this->actingAs($member)
        ->get(route('sprints.index', ['current_organization' => $organization]))
        ->assertForbidden();
});

test('completing a sprint returns unfinished issues to the backlog', function () {
    $admin = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($admin, 'Admin');
    $project = Project::factory()->for($organization)->create();
    $sprint = Sprint::factory()->for(Team::factory()->for($organization), 'owner')->active()->create();
    $done = Issue::factory()->for($project)->for($sprint)->create(['status' => IssueStatus::Done]);
    $unfinished = Issue::factory()->for($project)->for($sprint)->create(['status' => IssueStatus::InProgress]);

    $this->actingAs($admin)
        ->delete(route('sprints.complete', ['current_organization' => $organization, 'sprint' => $sprint]))
        ->assertRedirect();

    expect($sprint->fresh())
        ->status->toBe(SprintStatus::Completed)
        ->completed_at->not->toBeNull();
    expect($done->fresh()->sprint_id)->toBe($sprint->id);
    expect($unfinished->fresh())
        ->sprint_id->toBeNull()
        ->status->toBe(IssueStatus::Backlog);
});

test('only planned sprints can be started', function () {
    $admin = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($admin, 'Admin');
    $planned = Sprint::factory()->for(Team::factory()->for($organization), 'owner')->create();
    $completed = Sprint::factory()->for(Team::factory()->for($organization), 'owner')->completed()->create();

    $this->actingAs($admin)
        ->post(route('sprints.start', ['current_organization' => $organization, 'sprint' => $planned]))
        ->assertRedirect();

    expect($planned->fresh()->status)->toBe(SprintStatus::Active);

    $this->actingAs($admin)
        ->post(route('sprints.start', ['current_organization' => $organization, 'sprint' => $completed]))
        ->assertStatus(409);
});

test('a board column update sets status and order and plans backlog issues into the sprint', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');
    $project = Project::factory()->for($organization)->create();
    $team = Team::factory()->for($organization)->create();
    $team->members()->attach($member);
    $sprint = Sprint::factory()->for($team, 'owner')->active()->create();
    $inProgress = $team->columns()->where('status', IssueStatus::InProgress)->firstOrFail();
    $planned = Issue::factory()->for($project)->for($sprint)->create(['status' => IssueStatus::Todo]);
    $fromBacklog = Issue::factory()->for($project)->create();

    $this->actingAs($member)
        ->put(route('sprints.columns.update', ['current_organization' => $organization, 'sprint' => $sprint, 'column' => $inProgress]), [
            'issue_ids' => [$fromBacklog->id, $planned->id],
        ])
        ->assertRedirect();

    expect($fromBacklog->fresh())
        ->sprint_id->toBe($sprint->id)
        ->team_id->toBe($team->id)
        ->board_column_id->toBe($inProgress->id)
        ->status->toBe(IssueStatus::InProgress)
        ->position->toBe(0);
    expect($planned->fresh())
        ->board_column_id->toBe($inProgress->id)
        ->status->toBe(IssueStatus::InProgress)
        ->position->toBe(1);
});

test('board columns reject issues from another organization and columns of another team', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');
    $team = Team::factory()->for($organization)->create();
    $team->members()->attach($member);
    $sprint = Sprint::factory()->for($team, 'owner')->active()->create();
    $otherTeamColumn = Team::factory()->for($organization)->create()->columns()->firstOrFail();
    $foreignIssue = Issue::factory()->create();

    $this->actingAs($member)
        ->put(route('sprints.columns.update', ['current_organization' => $organization, 'sprint' => $sprint, 'column' => $team->columns()->firstOrFail()]), [
            'issue_ids' => [$foreignIssue->id],
        ])
        ->assertSessionHasErrors('issue_ids.0');

    $this->actingAs($member)
        ->put(route('sprints.columns.update', ['current_organization' => $organization, 'sprint' => $sprint, 'column' => $otherTeamColumn]), [
            'issue_ids' => [],
        ])
        ->assertNotFound();
});

test('sprint summaries total the points of their issues', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');
    $project = Project::factory()->for($organization)->create();
    $team = Team::factory()->for($organization)->create();
    $team->members()->attach($member);
    $sprint = Sprint::factory()->for($team, 'owner')->active()->create();
    Issue::factory()->for($project)->for($sprint)->create(['points' => 3]);
    Issue::factory()->for($project)->for($sprint)->create(['points' => 5]);
    Issue::factory()->for($project)->for($sprint)->create(['points' => null]);

    $this->actingAs($member)
        ->get(route('sprints.index', ['current_organization' => $organization]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('sprints.0.points', 8)
            ->where('sprints.0.issuesCount', 3),
        );
});

test('an organization with no teams gets a board of its own', function () {
    $organization = Organization::factory()->create();

    expect($organization->columns()->pluck('status')->all())
        ->toBe([IssueStatus::Todo, IssueStatus::InProgress, IssueStatus::Done]);
});

test('an issue planned into an organization sprint lands on the organization board', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');
    $project = Project::factory()->for($organization)->create();

    $sprint = Sprint::factory()->for($organization, 'owner')->create();
    $issue = Issue::factory()->for($project)->create(['status' => IssueStatus::Todo]);

    $issue->planInto($sprint);

    $issue->refresh();

    expect($issue->team_id)->toBeNull();
    expect($issue->boardColumn->owner->is($organization))->toBeTrue();
    expect($issue->status)->toBe(IssueStatus::Todo);
});

test('deleting a sprint returns its issues to the backlog without a column', function () {
    $admin = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($admin, 'Admin');
    $project = Project::factory()->for($organization)->create();

    $sprint = Sprint::factory()->for($organization, 'owner')->active()->create();
    $issue = Issue::factory()->for($project)->for($sprint)->create([
        'board_column_id' => $organization->columns->firstWhere('status', IssueStatus::InProgress)->id,
        'status' => IssueStatus::InProgress,
    ]);

    $this
        ->actingAs($admin)
        ->delete(route('sprints.destroy', ['current_organization' => $organization, 'sprint' => $sprint]))
        ->assertRedirect();

    $this->assertModelMissing($sprint);

    $issue->refresh();
    expect($issue->sprint_id)->toBeNull();
    expect($issue->board_column_id)->toBeNull();
    expect($issue->status)->toBe(IssueStatus::Backlog);
});

test('deleting a sprint from its own page lands on the sprints index', function () {
    $admin = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($admin, 'Admin');

    $sprint = Sprint::factory()->for($organization, 'owner')->create();
    $ownPage = route('sprints.show', ['current_organization' => $organization, 'sprint' => $sprint]);

    $this
        ->actingAs($admin)
        ->from($ownPage)
        ->delete(route('sprints.destroy', ['current_organization' => $organization, 'sprint' => $sprint]))
        ->assertRedirect(route('sprints.index', ['current_organization' => $organization]));
});

test('deleting a sprint from a list leaves the user on it', function () {
    $admin = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($admin, 'Admin');

    $sprint = Sprint::factory()->for($organization, 'owner')->create();
    $issues = route('issues.index', ['current_organization' => $organization]);

    $this
        ->actingAs($admin)
        ->from($issues)
        ->delete(route('sprints.destroy', ['current_organization' => $organization, 'sprint' => $sprint]))
        ->assertRedirect($issues);
});

test('a sprint can be renamed without touching its dates', function () {
    $admin = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($admin, 'Admin');

    $sprint = Sprint::factory()->for($organization, 'owner')->create(['name' => 'Sprint 2']);

    $this
        ->actingAs($admin)
        ->patch(route('sprints.update', ['current_organization' => $organization, 'sprint' => $sprint]), [
            'name' => 'Hardening',
            'starts_at' => $sprint->starts_at->toDateString(),
            'ends_at' => $sprint->ends_at->toDateString(),
        ])
        ->assertRedirect();

    expect($sprint->fresh())
        ->name->toBe('Hardening')
        ->starts_at->toDateString()->toBe($sprint->starts_at->toDateString());
});
