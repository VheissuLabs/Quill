<?php

use App\Enums\FeatureStatus;
use App\Enums\IssuePriority;
use App\Enums\IssueStatus;
use App\Enums\OrganizationRole;
use App\Models\Feature;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('members can create issues, numbered per project', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, OrganizationRole::Member);
    $project = Project::factory()->for($organization)->create();
    Issue::factory()->for($project)->create(['number' => 4]);

    $response = $this
        ->actingAs($member)
        ->post(route('issues.store', ['current_organization' => $organization]), [
            'project_id' => $project->id,
            'title' => 'Fix the diff viewer',
            'description' => 'It flashes white.',
            'priority' => IssuePriority::High->value,
        ]);

    $issue = Issue::firstWhere('title', 'Fix the diff viewer');

    $response->assertRedirect(route('issues.show', ['current_organization' => $organization, 'issue' => $issue]));

    expect($issue)
        ->number->toBe(5)
        ->status->toBe(IssueStatus::Backlog)
        ->priority->toBe(IssuePriority::High)
        ->created_by->toBe($member->id);
});

test('an issue created into a sprint starts as todo on the sprint team', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, OrganizationRole::Member);
    $project = Project::factory()->for($organization)->create();
    $sprint = Sprint::factory()->for(Team::factory()->for($organization))->create();

    $this->actingAs($member)
        ->post(route('issues.store', ['current_organization' => $organization]), [
            'project_id' => $project->id,
            'title' => 'Planned work',
            'priority' => IssuePriority::None->value,
            'sprint_id' => $sprint->id,
        ]);

    expect(Issue::firstWhere('title', 'Planned work'))
        ->status->toBe(IssueStatus::Todo)
        ->team_id->toBe($sprint->team_id);
});

test('issues cannot be created on projects of another organization', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, OrganizationRole::Member);
    $foreignProject = Project::factory()->create();

    $response = $this
        ->actingAs($member)
        ->post(route('issues.store', ['current_organization' => $organization]), [
            'project_id' => $foreignProject->id,
            'title' => 'Leak',
            'priority' => IssuePriority::None->value,
        ]);

    $response->assertSessionHasErrors('project_id');
});

test('the issues index hides closed issues by default and can filter by status', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, OrganizationRole::Member);
    $project = Project::factory()->for($organization)->create();
    Issue::factory()->for($project)->create(['title' => 'Open work']);
    Issue::factory()->for($project)->create(['title' => 'Shipped', 'status' => IssueStatus::Done]);

    $this->actingAs($member)
        ->get(route('issues.index', ['current_organization' => $organization]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('issues/Index')
            ->has('issues', 1)
            ->where('issues.0.title', 'Open work'),
        );

    $this->actingAs($member)
        ->get(route('issues.index', ['current_organization' => $organization, 'status' => 'done']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('issues', 1)
            ->where('issues.0.title', 'Shipped'),
        );
});

test('issues of another organization cannot be reached through the current organization', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, OrganizationRole::Member);
    $foreignIssue = Issue::factory()->create();

    $this->actingAs($member)
        ->get(route('issues.show', ['current_organization' => $organization, 'issue' => $foreignIssue]))
        ->assertNotFound();
});

test('marking a converted issue done completes its feature', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, OrganizationRole::Member);
    $project = Project::factory()->for($organization)->create();
    $feature = Feature::factory()->for($project)->create(['status' => FeatureStatus::Planned]);
    $issue = Issue::factory()->for($project)->for($feature)->create(['status' => IssueStatus::InProgress]);

    $this->actingAs($member)
        ->patch(route('issues.update', ['current_organization' => $organization, 'issue' => $issue]), [
            'status' => IssueStatus::Done->value,
        ])
        ->assertRedirect();

    expect($issue->fresh())
        ->status->toBe(IssueStatus::Done)
        ->completed_at->not->toBeNull();
    expect($feature->fresh()->status)->toBe(FeatureStatus::Completed);
});

test('planning an issue into a sprint moves it to the sprint team and out of the backlog', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, OrganizationRole::Member);
    $project = Project::factory()->for($organization)->create();
    $issue = Issue::factory()->for($project)->create();
    $sprint = Sprint::factory()->for(Team::factory()->for($organization))->create();

    $this->actingAs($member)
        ->patch(route('issues.update', ['current_organization' => $organization, 'issue' => $issue]), [
            'sprint_id' => $sprint->id,
        ])
        ->assertRedirect();

    expect($issue->fresh())
        ->sprint_id->toBe($sprint->id)
        ->team_id->toBe($sprint->team_id)
        ->status->toBe(IssueStatus::Todo);

    $this->actingAs($member)
        ->patch(route('issues.update', ['current_organization' => $organization, 'issue' => $issue]), [
            'sprint_id' => null,
        ])
        ->assertRedirect();

    expect($issue->fresh())
        ->sprint_id->toBeNull()
        ->status->toBe(IssueStatus::Backlog);
});

test('issues can be deleted by admins but not members', function () {
    $organization = Organization::factory()->create();
    $project = Project::factory()->for($organization)->create();
    $issue = Issue::factory()->for($project)->create();

    $member = User::factory()->create();
    $organization->addMember($member, OrganizationRole::Member);
    $admin = User::factory()->create();
    $organization->addMember($admin, OrganizationRole::Admin);

    $this->actingAs($member)
        ->delete(route('issues.destroy', ['current_organization' => $organization, 'issue' => $issue]))
        ->assertForbidden();

    $this->actingAs($admin)
        ->delete(route('issues.destroy', ['current_organization' => $organization, 'issue' => $issue]))
        ->assertRedirect(route('issues.index', ['current_organization' => $organization]));

    $this->assertModelMissing($issue);
});

test('issues can be ranked by dragging them into order', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, OrganizationRole::Member);
    $project = Project::factory()->for($organization)->create();
    $first = Issue::factory()->for($project)->create();
    $second = Issue::factory()->for($project)->create();
    $third = Issue::factory()->for($project)->create();

    $this->actingAs($member)
        ->put(route('issues.order.update', ['current_organization' => $organization]), [
            'issue_ids' => [$third->id, $first->id, $second->id],
        ])
        ->assertRedirect();

    expect($third->fresh()->position)->toBe(0);
    expect($first->fresh()->position)->toBe(1);
    expect($second->fresh()->position)->toBe(2);
});

test('moving an issue to a board column sets its status and planning into a sprint uses the todo column', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, OrganizationRole::Member);
    $project = Project::factory()->for($organization)->create();
    $team = Team::factory()->for($organization)->create();
    $sprint = Sprint::factory()->for($team)->active()->create();
    $issue = Issue::factory()->for($project)->create(['points' => 3]);

    $this->actingAs($member)
        ->patch(route('issues.update', ['current_organization' => $organization, 'issue' => $issue]), ['sprint_id' => $sprint->id])
        ->assertRedirect();

    expect($issue->fresh())
        ->board_column_id->toBe($team->columns()->where('status', IssueStatus::Todo)->value('id'))
        ->status->toBe(IssueStatus::Todo);

    $done = $team->columns()->where('status', IssueStatus::Done)->firstOrFail();

    $this->actingAs($member)
        ->patch(route('issues.update', ['current_organization' => $organization, 'issue' => $issue]), ['board_column_id' => $done->id])
        ->assertRedirect();

    expect($issue->fresh())
        ->board_column_id->toBe($done->id)
        ->status->toBe(IssueStatus::Done);
});

test('cancelling an issue removes it from its sprint', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, OrganizationRole::Member);
    $project = Project::factory()->for($organization)->create();
    $sprint = Sprint::factory()->for(Team::factory()->for($organization))->active()->create();
    $issue = Issue::factory()->for($project)->create();
    $issue->planInto($sprint);

    $this->actingAs($member)
        ->patch(route('issues.update', ['current_organization' => $organization, 'issue' => $issue]), ['status' => IssueStatus::Cancelled->value])
        ->assertRedirect();

    expect($issue->fresh())
        ->status->toBe(IssueStatus::Cancelled)
        ->sprint_id->toBeNull()
        ->board_column_id->toBeNull();
});

test('the issues index shows only unplanned issues, planned ones appear inside their sprint', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, OrganizationRole::Member);
    $project = Project::factory()->for($organization)->create();
    $sprint = Sprint::factory()->for(Team::factory()->for($organization))->active()->create();
    Issue::factory()->for($project)->create(['title' => 'Unplanned']);
    Issue::factory()->for($project)->for($sprint)->create(['title' => 'Planned', 'status' => IssueStatus::Todo]);

    $this->actingAs($member)
        ->get(route('issues.index', ['current_organization' => $organization]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('issues', 1)
            ->where('issues.0.title', 'Unplanned')
            ->where('sprints.0.issues.0.title', 'Planned'),
        );
});

test('the backlog is scoped to the current team plus issues with no team yet', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, OrganizationRole::Member);
    $project = Project::factory()->for($organization)->create();
    $development = Team::factory()->for($organization)->create(['name' => 'Development']);
    $marketing = Team::factory()->for($organization)->create(['name' => 'Marketing']);
    Issue::factory()->for($project)->for($development)->create(['title' => 'Dev work']);
    Issue::factory()->for($project)->for($marketing)->create(['title' => 'Campaign']);
    Issue::factory()->for($project)->create(['title' => 'Untriaged']);
    Sprint::factory()->for($development)->create(['name' => 'Dev sprint']);
    Sprint::factory()->for($marketing)->create(['name' => 'Marketing sprint']);
    $member->switchOrganization($organization);
    $member->switchTeam($development);

    $this->actingAs($member)
        ->get(route('issues.index', ['current_organization' => $organization]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('issues', 2)
            ->where('issues', fn (\Illuminate\Support\Collection $issues) => $issues->pluck('title')->sort()->values()->all() === ['Dev work', 'Untriaged'])
            ->has('sprints', 1)
            ->where('sprints.0.name', 'Dev sprint'),
        );
});

test('users can switch their current team within the organization', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, OrganizationRole::Member);
    $team = Team::factory()->for($organization)->create();
    $foreignTeam = Team::factory()->create();
    $member->switchOrganization($organization);

    $this->actingAs($member)
        ->post(route('teams.switch', ['current_organization' => $organization, 'team' => $team]))
        ->assertRedirect();

    expect($member->fresh()->current_team_id)->toBe($team->id);

    $this->actingAs($member)
        ->post(route('teams.switch', ['current_organization' => $organization, 'team' => $foreignTeam]))
        ->assertNotFound();
});
