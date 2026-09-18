<?php

use App\Enums\FeedbackStatus;
use App\Enums\IssuePriority;
use App\Enums\IssueStatus;
use App\Models\Feedback;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

test('members can create issues, numbered per project', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');
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
    $organization->addMember($member, 'Member');
    $project = Project::factory()->for($organization)->create();
    $sprint = Sprint::factory()->for(Team::factory()->for($organization), 'owner')->create();

    $this->actingAs($member)
        ->post(route('issues.store', ['current_organization' => $organization]), [
            'project_id' => $project->id,
            'title' => 'Planned work',
            'priority' => IssuePriority::None->value,
            'sprint_id' => $sprint->id,
        ]);

    expect(Issue::firstWhere('title', 'Planned work'))
        ->status->toBe(IssueStatus::Todo)
        ->team_id->toBe($sprint->teamId());
});

test('issues cannot be created on projects of another organization', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');
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
    $organization->addMember($member, 'Member');
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

test('issues cannot be viewed when the issues module is disabled', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->updateModuleSettings([
        'development_enabled' => false,
        'development_issues_enabled' => true,
    ]);
    $organization->addMember($member, 'Member');

    $this->actingAs($member)
        ->get(route('issues.index', ['current_organization' => $organization]))
        ->assertForbidden();
});

test('issues of another organization cannot be reached through the current organization', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');
    $foreignIssue = Issue::factory()->create();

    $this->actingAs($member)
        ->get(route('issues.show', ['current_organization' => $organization, 'issue' => $foreignIssue]))
        ->assertNotFound();
});

test('marking a converted issue done completes its feedback', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');
    $project = Project::factory()->for($organization)->create();
    $feedback = Feedback::factory()->for($project)->create(['status' => FeedbackStatus::Planned]);
    $issue = Issue::factory()->for($project)->for($feedback)->create(['status' => IssueStatus::InProgress]);

    $this->actingAs($member)
        ->patch(route('issues.update', ['current_organization' => $organization, 'issue' => $issue]), [
            'status' => IssueStatus::Done->value,
        ])
        ->assertRedirect();

    expect($issue->fresh())
        ->status->toBe(IssueStatus::Done)
        ->completed_at->not->toBeNull();
    expect($feedback->fresh()->status)->toBe(FeedbackStatus::Completed);
});

test('a converted issue keeps its feedback status in step as it moves across the board', function () {
    $organization = Organization::factory()->create();
    $member = User::factory()->create();
    $organization->addMember($member, 'Member');
    $project = Project::factory()->for($organization)->create();
    $team = Team::factory()->for($organization)->create();
    $feedback = Feedback::factory()->for($project)->create(['status' => FeedbackStatus::Planned]);
    $issue = Issue::factory()->for($project)->for($feedback)->create(['team_id' => $team->id]);

    $moveTo = fn (array $payload) => $this->actingAs($member)
        ->patch(route('issues.update', ['current_organization' => $organization, 'issue' => $issue]), $payload)
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $moveTo(['board_column_id' => $team->columns()->where('status', IssueStatus::InProgress)->value('id')]);
    expect($feedback->fresh()->status)->toBe(FeedbackStatus::InProgress);

    $moveTo(['board_column_id' => $team->columns()->where('status', IssueStatus::Todo)->value('id')]);
    expect($feedback->fresh()->status)->toBe(FeedbackStatus::Planned);

    $moveTo(['status' => IssueStatus::Cancelled->value]);
    expect($feedback->fresh()->status)->toBe(FeedbackStatus::Planned);
});

test('the issue page offers only the sprints of the issue team, or of the viewer team for unplanned issues', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');
    $project = Project::factory()->for($organization)->create();
    $engineering = Team::factory()->for($organization)->create(['name' => 'Engineering']);
    $design = Team::factory()->for($organization)->create(['name' => 'Design']);
    Sprint::factory()->for($engineering, 'owner')->create(['name' => 'Engineering sprint']);
    Sprint::factory()->for($design, 'owner')->create(['name' => 'Design sprint']);

    $designIssue = Issue::factory()->for($project)->create(['team_id' => $design->id]);
    $unplanned = Issue::factory()->for($project)->create();

    $member->switchOrganization($organization);
    $member->switchTeam($engineering);

    $this->actingAs($member)
        ->get(route('issues.show', ['current_organization' => $organization, 'issue' => $designIssue]))
        ->assertInertia(fn (Assert $page) => $page->has('sprints', 1)->where('sprints.0.name', 'Design sprint'));

    $this->actingAs($member)
        ->get(route('issues.show', ['current_organization' => $organization, 'issue' => $unplanned]))
        ->assertInertia(fn (Assert $page) => $page->has('sprints', 1)->where('sprints.0.name', 'Engineering sprint'));
});

test('planning an issue into a sprint moves it to the sprint team and out of the backlog', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');
    $project = Project::factory()->for($organization)->create();
    $issue = Issue::factory()->for($project)->create();
    $sprint = Sprint::factory()->for(Team::factory()->for($organization), 'owner')->create();

    $this->actingAs($member)
        ->patch(route('issues.update', ['current_organization' => $organization, 'issue' => $issue]), [
            'sprint_id' => $sprint->id,
        ])
        ->assertRedirect();

    expect($issue->fresh())
        ->sprint_id->toBe($sprint->id)
        ->team_id->toBe($sprint->teamId())
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
    $organization->addMember($member, 'Member');
    $admin = User::factory()->create();
    $organization->addMember($admin, 'Admin');

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
    $organization->addMember($member, 'Member');
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
    $organization->addMember($member, 'Member');
    $project = Project::factory()->for($organization)->create();
    $team = Team::factory()->for($organization)->create();
    $sprint = Sprint::factory()->for($team, 'owner')->active()->create();
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
    $organization->addMember($member, 'Member');
    $project = Project::factory()->for($organization)->create();
    $sprint = Sprint::factory()->for(Team::factory()->for($organization), 'owner')->active()->create();
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
    $organization->addMember($member, 'Member');
    $project = Project::factory()->for($organization)->create();
    $sprint = Sprint::factory()->for(Team::factory()->for($organization), 'owner')->active()->create();
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
    $organization->addMember($member, 'Member');
    $project = Project::factory()->for($organization)->create();
    $development = Team::factory()->for($organization)->create(['name' => 'Development']);
    $marketing = Team::factory()->for($organization)->create(['name' => 'Marketing']);
    Issue::factory()->for($project)->for($development)->create(['title' => 'Dev work']);
    Issue::factory()->for($project)->for($marketing)->create(['title' => 'Campaign']);
    Issue::factory()->for($project)->create(['title' => 'Untriaged']);
    Sprint::factory()->for($development, 'owner')->create(['name' => 'Dev sprint']);
    Sprint::factory()->for($marketing, 'owner')->create(['name' => 'Marketing sprint']);
    $member->switchOrganization($organization);
    $member->switchTeam($development);

    $this->actingAs($member)
        ->get(route('issues.index', ['current_organization' => $organization]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('issues', 2)
            ->where('issues', fn (Collection $issues) => $issues->pluck('title')->sort()->values()->all() === ['Dev work', 'Untriaged'])
            ->has('sprints', 1)
            ->where('sprints.0.name', 'Dev sprint'),
        );
});

test('users can switch their current team within the organization', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');
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

test('an update cannot combine conflicting placements', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');
    $project = Project::factory()->for($organization)->create();
    $team = Team::factory()->for($organization)->create();
    $sprint = Sprint::factory()->for($team, 'owner')->active()->create();
    $issue = Issue::factory()->for($project)->create();
    $column = $team->columns()->where('status', IssueStatus::Done)->firstOrFail();

    $this->actingAs($member)
        ->patch(route('issues.update', ['current_organization' => $organization, 'issue' => $issue]), [
            'sprint_id' => $sprint->id,
            'board_column_id' => $column->id,
        ])
        ->assertSessionHasErrors('board_column_id');

    expect($issue->fresh())
        ->sprint_id->toBeNull()
        ->board_column_id->toBeNull()
        ->status->toBe(IssueStatus::Backlog);
});

test('the issue board filters by a search term on the title', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');
    $project = Project::factory()->for($organization)->create();
    Issue::factory()->for($project)->create(['title' => 'Orbit drifts on resize', 'number' => 1]);
    Issue::factory()->for($project)->create(['title' => 'Unrelated work', 'number' => 2]);

    $this->actingAs($member)
        ->get(route('issues.index', ['current_organization' => $organization, 'q' => 'orbit']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.search', 'orbit')
            ->has('issues', 1)
            ->where('issues.0.title', 'Orbit drifts on resize'));
});

test('an issue search term matching nothing returns an empty board', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');
    $project = Project::factory()->for($organization)->create();
    Issue::factory()->for($project)->create(['title' => 'Unrelated work', 'number' => 1]);

    $this->actingAs($member)
        ->get(route('issues.index', ['current_organization' => $organization, 'q' => 'nothing like this']))
        ->assertInertia(fn (Assert $page) => $page->has('issues', 0));
});

test('an issue search term combines with the project filter', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');
    $wanted = Project::factory()->for($organization)->create(['name' => 'Atlas']);
    $other = Project::factory()->for($organization)->create(['name' => 'Beacon']);
    Issue::factory()->for($wanted)->create(['title' => 'Shared name', 'number' => 1]);
    Issue::factory()->for($other)->create(['title' => 'Shared name', 'number' => 1]);

    $this->actingAs($member)
        ->get(route('issues.index', ['current_organization' => $organization, 'q' => 'shared', 'project' => $wanted->slug]))
        ->assertInertia(fn (Assert $page) => $page->has('issues', 1));
});

test('the issue board exposes the assignee id so the viewer can be highlighted', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');
    $project = Project::factory()->for($organization)->create();
    Issue::factory()->for($project)->create(['assignee_id' => $member->id, 'number' => 1]);
    Issue::factory()->for($project)->create(['assignee_id' => null, 'number' => 2]);

    $this->actingAs($member)
        ->get(route('issues.index', ['current_organization' => $organization]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('issues', 2)
            ->where('issues.0.assigneeId', fn ($id) => $id === $member->id || $id === null)
            ->has('issues.0.assigneeId'));
});

test('a row reports the stage using the teams own column name', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');
    $team = Team::factory()->for($organization)->create();
    $team->members()->attach($member);
    $project = Project::factory()->for($organization)->create();

    $column = $team->columns()->where('status', IssueStatus::InProgress)->firstOrFail();
    $column->update(['name' => 'Cooking']);

    Issue::factory()->for($project)->create([
        'team_id' => $team->id,
        'status' => IssueStatus::InProgress,
        'number' => 1,
    ]);

    $this->actingAs($member)
        ->get(route('issues.index', ['current_organization' => $organization, 'status' => 'in_progress']))
        ->assertInertia(fn (Assert $page) => $page->where('issues.0.statusLabel', 'Cooking'));
});

test('a backlog row keeps its plain label when no column matches', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');
    $team = Team::factory()->for($organization)->create();
    $team->members()->attach($member);
    $project = Project::factory()->for($organization)->create();

    Issue::factory()->for($project)->create([
        'team_id' => $team->id,
        'status' => IssueStatus::Backlog,
        'number' => 1,
    ]);

    $this->actingAs($member)
        ->get(route('issues.index', ['current_organization' => $organization]))
        ->assertInertia(fn (Assert $page) => $page->where('issues.0.statusLabel', 'Backlog'));
});

test('the issue page offers the teams columns whether or not it is in a sprint', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');
    $team = Team::factory()->for($organization)->create();
    $team->members()->attach($member);
    $project = Project::factory()->for($organization)->create();

    $issue = Issue::factory()->for($project)->create([
        'team_id' => $team->id,
        'status' => IssueStatus::Backlog,
        'number' => 1,
    ]);

    $this->actingAs($member)
        ->get(route('issues.show', ['current_organization' => $organization, 'issue' => $issue]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('issue.columns', $team->columns()->count())
            ->where('issue.sprintId', null));
});

test('an unplanned issue can be moved straight into a column', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');
    $team = Team::factory()->for($organization)->create();
    $team->members()->attach($member);
    $project = Project::factory()->for($organization)->create();
    $issue = Issue::factory()->for($project)->create(['status' => IssueStatus::Backlog, 'number' => 1]);
    $column = $team->columns()->where('status', IssueStatus::InProgress)->firstOrFail();

    $this->actingAs($member)
        ->patch(route('issues.update', ['current_organization' => $organization, 'issue' => $issue]), [
            'board_column_id' => $column->id,
        ])
        ->assertRedirect();

    expect($issue->fresh())
        ->board_column_id->toBe($column->id)
        ->status->toBe(IssueStatus::InProgress)
        ->team_id->toBe($team->id);
});

test('the issues index offers each row the columns of the board it belongs to', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');
    $project = Project::factory()->for($organization)->create();
    $team = Team::factory()->for($organization)->create();
    $team->members()->attach($member);
    Issue::factory()->for($project)->create(['team_id' => $team->id, 'status' => IssueStatus::Todo]);

    $this->actingAs($member)
        ->get(route('issues.index', ['current_organization' => $organization]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('issues/Index')
            ->where('issues.0.teamId', $team->id)
            ->where('teams.0.id', $team->id)
            ->where(
                'teams.0.columns',
                fn ($columns) => collect($columns)->pluck('name')->all()
                    === $team->columns->pluck('name')->all()
            )
            ->has('boardColumns', $organization->columns()->count()),
        );
});
