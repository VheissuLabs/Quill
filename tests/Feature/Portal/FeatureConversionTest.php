<?php

use App\Enums\FeedbackStatus;
use App\Enums\IssueStatus;
use App\Models\Feedback;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('admins convert a feedback into a backlog issue and the feedback becomes planned', function () {
    $project = Project::factory()->public()->create();
    $feedback = Feedback::factory()->for($project)->create(['title' => 'Dark mode', 'description' => 'Please.']);
    $admin = User::factory()->create();
    $project->organization->addMember($admin, 'Admin');

    $response = $this
        ->actingAs($admin)
        ->post(route('portal.feedback.issue.store', [$project->organization, $project, $feedback]));

    $issue = Issue::firstWhere('feedback_id', $feedback->id);

    $response->assertRedirect(route('issues.show', ['current_organization' => $project->organization, 'issue' => $issue]));

    expect($issue)
        ->project_id->toBe($project->id)
        ->title->toBe('Dark mode')
        ->description->toBe('Please.')
        ->status->toBe(IssueStatus::Backlog)
        ->sprint_id->toBeNull()
        ->created_by->toBe($admin->id);
    expect($feedback->fresh()->status)->toBe(FeedbackStatus::Planned);
});

test('a feedback can only be converted once', function () {
    $project = Project::factory()->public()->create();
    $feedback = Feedback::factory()->for($project)->create();
    Issue::factory()->for($project)->for($feedback)->create();
    $admin = User::factory()->create();
    $project->organization->addMember($admin, 'Admin');

    $this->actingAs($admin)
        ->post(route('portal.feedback.issue.store', [$project->organization, $project, $feedback]))
        ->assertStatus(409);
});

test('members cannot convert feedback', function () {
    $project = Project::factory()->public()->create();
    $feedback = Feedback::factory()->for($project)->create();
    $member = User::factory()->create();
    $project->organization->addMember($member, 'Member');

    $this->actingAs($member)
        ->post(route('portal.feedback.issue.store', [$project->organization, $project, $feedback]))
        ->assertForbidden();
});

test('members see the linked issue on the feedback page but the public does not', function () {
    $project = Project::factory()->public()->create();
    $feedback = Feedback::factory()->for($project)->create();
    $issue = Issue::factory()->for($project)->for($feedback)->create();
    $member = User::factory()->create();
    $project->organization->addMember($member, 'Member');

    $this->get(route('portal.feedback.show', [$project->organization, $project, $feedback]))
        ->assertInertia(fn (Assert $page) => $page->where('issue', null));

    $this->actingAs($member)
        ->get(route('portal.feedback.show', [$project->organization, $project, $feedback]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('issue.id', $issue->id)
            ->where('issue.reference', $issue->reference()),
        );
});

test('declined and completed feedback cannot be converted', function () {
    $project = Project::factory()->public()->create();
    $admin = User::factory()->create();
    $project->organization->addMember($admin, 'Admin');

    foreach ([FeedbackStatus::Declined, FeedbackStatus::Completed] as $status) {
        $feedback = Feedback::factory()->for($project)->create(['status' => $status]);

        $this->actingAs($admin)
            ->post(route('portal.feedback.issue.store', [$project->organization, $project, $feedback]))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('portal.feedback.show', [$project->organization, $project, $feedback]))
            ->assertInertia(fn (Assert $page) => $page->where('viewer.canConvert', false));
    }
});
