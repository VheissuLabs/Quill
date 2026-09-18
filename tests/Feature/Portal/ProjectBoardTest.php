<?php

use App\Enums\FeedbackStatus;
use App\Models\Feedback;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use App\Models\Vote;
use Inertia\Testing\AssertableInertia as Assert;

test('public boards can be viewed by guests', function () {
    $project = Project::factory()->public()->create();
    Feedback::factory()->for($project)->create(['title' => 'Dark mode']);

    $response = $this->get(route('portal.projects.show', [$project->organization, $project]));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('portal/Project')
            ->has('feedback', 1)
            ->where('feedback.0.title', 'Dark mode')
            ->where('viewer.canCreateFeedback', false),
        );
});

test('public boards cannot be viewed when feedback voting is disabled', function () {
    $project = Project::factory()->public()->create();
    $project->organization->updateModuleSettings([
        'feedback_enabled' => false,
        'feedback_boards_enabled' => true,
    ]);

    $this->get(route('portal.projects.show', [$project->organization, $project]))
        ->assertForbidden();
});

test('private boards redirect guests to login', function () {
    $project = Project::factory()->create();

    $response = $this->get(route('portal.projects.show', [$project->organization, $project]));

    $response->assertRedirect(route('login'));
});

test('private boards are forbidden for users outside the organization', function () {
    $outsider = User::factory()->create();
    $project = Project::factory()->create();

    $response = $this
        ->actingAs($outsider)
        ->get(route('portal.projects.show', [$project->organization, $project]));

    $response->assertForbidden();
});

test('private boards can be viewed by organization members', function () {
    $member = User::factory()->create();
    $project = Project::factory()->create();
    $project->organization->addMember($member, 'Member');

    $response = $this
        ->actingAs($member)
        ->get(route('portal.projects.show', [$project->organization, $project]));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('viewer.isMember', true)
            ->where('viewer.canCreateFeedback', true)
            ->where('viewer.canManageFeedback', false),
        );
});

test('team restricted boards are only visible to members of those teams and admins', function () {
    $organization = Organization::factory()->create();
    $project = Project::factory()->for($organization)->create();
    $team = Team::factory()->for($organization)->create();
    $project->teams()->attach($team);

    $teamMember = User::factory()->create();
    $organization->addMember($teamMember, 'Member');
    $team->members()->attach($teamMember);

    $otherMember = User::factory()->create();
    $organization->addMember($otherMember, 'Member');

    $admin = User::factory()->create();
    $organization->addMember($admin, 'Admin');

    $this->actingAs($teamMember)->get(route('portal.projects.show', [$organization, $project]))->assertOk();
    $this->actingAs($otherMember)->get(route('portal.projects.show', [$organization, $project]))->assertForbidden();
    $this->actingAs($admin)->get(route('portal.projects.show', [$organization, $project]))->assertOk();
});

test('feedback are ordered by net score and can be filtered by status', function () {
    $project = Project::factory()->public()->create();
    $popular = Feedback::factory()->for($project)->create(['title' => 'Popular']);
    $quiet = Feedback::factory()->for($project)->create(['title' => 'Quiet']);
    $disliked = Feedback::factory()->for($project)->create(['title' => 'Disliked']);
    Vote::factory()->count(2)->for($popular)->create();
    Vote::factory()->count(3)->for($disliked)->create();
    Vote::factory()->count(5)->for($disliked)->down()->create();

    $this->get(route('portal.projects.show', [$project->organization, $project]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('feedback.0.title', 'Popular')
            ->where('feedback.0.votesCount', 2)
            ->where('feedback.1.title', 'Quiet')
            ->where('feedback.2.title', 'Disliked')
            ->where('feedback.2.votesCount', 3)
            ->where('feedback.2.downvotesCount', 5)
            ->where('feedback.2.score', -2),
        );

    $quiet->update(['status' => 'planned']);

    $this->get(route('portal.projects.show', [$project->organization, $project, 'status' => 'planned']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('feedback', 1)
            ->where('feedback.0.title', 'Quiet')
            ->where('filters.status', 'planned'),
        );
});

test('members see the tracked issue on the board but guests do not', function () {
    $project = Project::factory()->public()->create();
    $feedback = Feedback::factory()->for($project)->create();
    $issue = Issue::factory()->for($project)->for($feedback)->create();

    $this->get(route('portal.projects.show', [$project->organization, $project]))
        ->assertInertia(fn (Assert $page) => $page->where('feedback.0.issue', null));

    $member = User::factory()->create();
    $project->organization->addMember($member, 'Member');

    $this->actingAs($member)
        ->get(route('portal.projects.show', [$project->organization, $project]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('feedback.0.issue.id', $issue->id)
            ->where('feedback.0.issue.reference', $issue->reference()),
        );
});

test('completed and declined requests leave the board but stay under their status filter', function () {
    $project = Project::factory()->public()->create();
    Feedback::factory()->for($project)->create(['title' => 'Still open']);
    Feedback::factory()->for($project)->create(['title' => 'Shipped', 'status' => FeedbackStatus::Completed]);
    Feedback::factory()->for($project)->create(['title' => 'Rejected', 'status' => FeedbackStatus::Declined]);

    $this->get(route('portal.projects.show', [$project->organization, $project]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('feedback', 1)
            ->where('feedback.0.title', 'Still open')
            ->where('activeCount', 1),
        );

    $this->get(route('portal.projects.show', [$project->organization, $project, 'status' => 'completed']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('feedback', 1)
            ->where('feedback.0.title', 'Shipped'),
        );
});

test('a project cannot be reached through another organization', function () {
    $project = Project::factory()->public()->create();
    $otherOrganization = Organization::factory()->create();

    $response = $this->get(route('portal.projects.show', [$otherOrganization, $project]));

    $response->assertNotFound();
});

test('signed in users outside the organization are not treated as members on public boards', function () {
    $outsider = User::factory()->create();
    $project = Project::factory()->public()->create();
    $feedback = Feedback::factory()->for($project)->create();

    $this->actingAs($outsider)
        ->get(route('portal.projects.show', [$project->organization, $project]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('viewer.isMember', false)
            ->where('viewer.canCreateFeedback', true)
            ->where('viewer.canManageFeedback', false),
        );

    $this->actingAs($outsider)
        ->get(route('portal.feedback.show', [$project->organization, $project, $feedback]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('viewer.isMember', false)
            ->where('viewer.canConvert', false)
            ->where('viewer.canCommentInternally', false)
            ->where('issue', null),
        );
});

test('the feedback board filters by a search term on the title', function () {
    $project = Project::factory()->public()->create();
    Feedback::factory()->for($project)->create(['title' => 'Dark mode please']);
    Feedback::factory()->for($project)->create(['title' => 'Unrelated request']);

    $this->get(route('portal.projects.show', [$project->organization, $project, 'q' => 'dark']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.search', 'dark')
            ->has('feedback', 1)
            ->where('feedback.0.title', 'Dark mode please'));
});

test('a feedback search term narrows within the selected status', function () {
    $project = Project::factory()->public()->create();
    Feedback::factory()->for($project)->create(['title' => 'Dark mode', 'status' => FeedbackStatus::Planned]);
    Feedback::factory()->for($project)->create(['title' => 'Dark theme', 'status' => FeedbackStatus::Open]);

    $this->get(route('portal.projects.show', [
        $project->organization,
        $project,
        'q' => 'dark',
        'status' => FeedbackStatus::Planned->value,
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('feedback', 1)
            ->where('feedback.0.title', 'Dark mode'));
});

test('the status counts ignore the search term so the sidebar stays stable', function () {
    $project = Project::factory()->public()->create();
    Feedback::factory()->for($project)->count(3)->create(['status' => FeedbackStatus::Open]);
    Feedback::factory()->for($project)->create(['title' => 'Dark mode', 'status' => FeedbackStatus::Open]);

    $this->get(route('portal.projects.show', [$project->organization, $project, 'q' => 'dark']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('feedback', 1)
            ->where('activeCount', 4));
});
