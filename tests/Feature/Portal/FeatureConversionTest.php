<?php

use App\Enums\FeatureStatus;
use App\Enums\IssueStatus;
use App\Enums\OrganizationRole;
use App\Models\Feature;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('admins convert a feature into a backlog issue and the feature becomes planned', function () {
    $project = Project::factory()->public()->create();
    $feature = Feature::factory()->for($project)->create(['title' => 'Dark mode', 'description' => 'Please.']);
    $admin = User::factory()->create();
    $project->organization->addMember($admin, OrganizationRole::Admin);

    $response = $this
        ->actingAs($admin)
        ->post(route('portal.features.issue.store', [$project->organization, $project, $feature]));

    $issue = Issue::firstWhere('feature_id', $feature->id);

    $response->assertRedirect(route('issues.show', ['current_organization' => $project->organization, 'issue' => $issue]));

    expect($issue)
        ->project_id->toBe($project->id)
        ->title->toBe('Dark mode')
        ->description->toBe('Please.')
        ->status->toBe(IssueStatus::Backlog)
        ->sprint_id->toBeNull()
        ->created_by->toBe($admin->id);
    expect($feature->fresh()->status)->toBe(FeatureStatus::Planned);
});

test('a feature can only be converted once', function () {
    $project = Project::factory()->public()->create();
    $feature = Feature::factory()->for($project)->create();
    Issue::factory()->for($project)->for($feature)->create();
    $admin = User::factory()->create();
    $project->organization->addMember($admin, OrganizationRole::Admin);

    $this->actingAs($admin)
        ->post(route('portal.features.issue.store', [$project->organization, $project, $feature]))
        ->assertStatus(409);
});

test('members cannot convert features', function () {
    $project = Project::factory()->public()->create();
    $feature = Feature::factory()->for($project)->create();
    $member = User::factory()->create();
    $project->organization->addMember($member, OrganizationRole::Member);

    $this->actingAs($member)
        ->post(route('portal.features.issue.store', [$project->organization, $project, $feature]))
        ->assertForbidden();
});

test('members see the linked issue on the feature page but the public does not', function () {
    $project = Project::factory()->public()->create();
    $feature = Feature::factory()->for($project)->create();
    $issue = Issue::factory()->for($project)->for($feature)->create();
    $member = User::factory()->create();
    $project->organization->addMember($member, OrganizationRole::Member);

    $this->get(route('portal.features.show', [$project->organization, $project, $feature]))
        ->assertInertia(fn (Assert $page) => $page->where('issue', null));

    $this->actingAs($member)
        ->get(route('portal.features.show', [$project->organization, $project, $feature]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('issue.id', $issue->id)
            ->where('issue.reference', $issue->reference()),
        );
});

test('declined and completed features cannot be converted', function () {
    $project = Project::factory()->public()->create();
    $admin = User::factory()->create();
    $project->organization->addMember($admin, OrganizationRole::Admin);

    foreach ([FeatureStatus::Declined, FeatureStatus::Completed] as $status) {
        $feature = Feature::factory()->for($project)->create(['status' => $status]);

        $this->actingAs($admin)
            ->post(route('portal.features.issue.store', [$project->organization, $project, $feature]))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('portal.features.show', [$project->organization, $project, $feature]))
            ->assertInertia(fn (Assert $page) => $page->where('viewer.canConvert', false));
    }
});
