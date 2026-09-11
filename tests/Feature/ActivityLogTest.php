<?php

use App\Enums\FeatureStatus;
use App\Enums\IssueStatus;
use App\Enums\OrganizationRole;
use App\Models\Feature;
use App\Models\Issue;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('feature status changes are logged with who made them and shown to members', function () {
    $project = Project::factory()->public()->create();
    $feature = Feature::factory()->for($project)->create(['status' => FeatureStatus::Open]);
    $admin = User::factory()->create(['name' => 'Karl Murray']);
    $project->organization->addMember($admin, OrganizationRole::Admin);

    $this->actingAs($admin)
        ->patch(route('portal.features.status.update', [$project->organization, $project, $feature]), ['status' => FeatureStatus::Planned->value])
        ->assertRedirect();

    $activity = $feature->activitiesAsSubject()->latest('id')->first();

    expect($activity)
        ->event->toBe('updated')
        ->log_name->toBe('features')
        ->causer_id->toBe($admin->id);
    expect($activity->attribute_changes['old']['status'])->toBe('open');
    expect($activity->attribute_changes['attributes']['status'])->toBe('planned');

    $this->actingAs($admin)
        ->get(route('portal.features.show', [$project->organization, $project, $feature]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('activity.0.causerName', 'Karl Murray')
            ->where('activity.0.changes.0.field', 'Status')
            ->where('activity.0.changes.0.from', 'Open')
            ->where('activity.0.changes.0.to', 'Planned'),
        );

    auth()->logout();

    $this->get(route('portal.features.show', [$project->organization, $project, $feature]))
        ->assertInertia(fn (Assert $page) => $page->where('activity', []));
});

test('planning an issue logs the sprint and status with related names resolved', function () {
    $member = User::factory()->create(['name' => 'Grace Hopper']);
    $organization = \App\Models\Organization::factory()->create();
    $organization->addMember($member, OrganizationRole::Admin);
    $project = Project::factory()->for($organization)->create();
    $team = Team::factory()->for($organization)->create(['name' => 'Development']);
    $sprint = Sprint::factory()->for($team)->active()->create(['name' => 'Sprint 4']);
    $issue = Issue::factory()->for($project)->create();

    $this->actingAs($member)
        ->patch(route('issues.update', ['current_organization' => $organization, 'issue' => $issue]), ['sprint_id' => $sprint->id])
        ->assertRedirect();

    expect($issue->fresh()->status)->toBe(IssueStatus::Todo);

    $this->actingAs($member)
        ->get(route('issues.show', ['current_organization' => $organization, 'issue' => $issue]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('activity.0.causerName', 'Grace Hopper')
            ->where('activity', fn ($entries) => collect($entries)
                ->flatMap(fn ($entry) => $entry['changes'])
                ->contains(fn ($change) => $change['field'] === 'Sprint' && $change['to'] === 'Sprint 4')),
        );
});

test('activity is only shown to people with the view activity permission', function () {
    $project = Project::factory()->public()->create();
    $feature = Feature::factory()->for($project)->create();
    $member = User::factory()->create();
    $project->organization->addMember($member, OrganizationRole::Member);
    $feature->update(['status' => FeatureStatus::Planned]);

    $this->actingAs($member)
        ->get(route('portal.features.show', [$project->organization, $project, $feature]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('viewer.canViewActivity', false)
            ->where('activity', []),
        );
});
