<?php

use App\Enums\OrganizationRole;
use App\Models\Feature;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use App\Models\Vote;
use Inertia\Testing\AssertableInertia as Assert;

test('public boards can be viewed by guests', function () {
    $project = Project::factory()->public()->create();
    Feature::factory()->for($project)->create(['title' => 'Dark mode']);

    $response = $this->get(route('portal.projects.show', [$project->organization, $project]));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('portal/Project')
            ->has('features', 1)
            ->where('features.0.title', 'Dark mode')
            ->where('viewer.canCreateFeature', false),
        );
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
    $project->organization->addMember($member, OrganizationRole::Member);

    $response = $this
        ->actingAs($member)
        ->get(route('portal.projects.show', [$project->organization, $project]));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('viewer.isMember', true)
            ->where('viewer.canCreateFeature', true)
            ->where('viewer.canManageFeatures', false),
        );
});

test('team restricted boards are only visible to members of those teams and admins', function () {
    $organization = Organization::factory()->create();
    $project = Project::factory()->for($organization)->create();
    $team = Team::factory()->for($organization)->create();
    $project->teams()->attach($team);

    $teamMember = User::factory()->create();
    $organization->addMember($teamMember, OrganizationRole::Member);
    $team->members()->attach($teamMember);

    $otherMember = User::factory()->create();
    $organization->addMember($otherMember, OrganizationRole::Member);

    $admin = User::factory()->create();
    $organization->addMember($admin, OrganizationRole::Admin);

    $this->actingAs($teamMember)->get(route('portal.projects.show', [$organization, $project]))->assertOk();
    $this->actingAs($otherMember)->get(route('portal.projects.show', [$organization, $project]))->assertForbidden();
    $this->actingAs($admin)->get(route('portal.projects.show', [$organization, $project]))->assertOk();
});

test('features are ordered by net score and can be filtered by status', function () {
    $project = Project::factory()->public()->create();
    $popular = Feature::factory()->for($project)->create(['title' => 'Popular']);
    $quiet = Feature::factory()->for($project)->create(['title' => 'Quiet']);
    $disliked = Feature::factory()->for($project)->create(['title' => 'Disliked']);
    Vote::factory()->count(2)->for($popular)->create();
    Vote::factory()->count(3)->for($disliked)->create();
    Vote::factory()->count(5)->for($disliked)->down()->create();

    $this->get(route('portal.projects.show', [$project->organization, $project]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('features.0.title', 'Popular')
            ->where('features.0.votesCount', 2)
            ->where('features.1.title', 'Quiet')
            ->where('features.2.title', 'Disliked')
            ->where('features.2.votesCount', 3)
            ->where('features.2.downvotesCount', 5)
            ->where('features.2.score', -2),
        );

    $quiet->update(['status' => 'planned']);

    $this->get(route('portal.projects.show', [$project->organization, $project, 'status' => 'planned']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('features', 1)
            ->where('features.0.title', 'Quiet')
            ->where('filters.status', 'planned'),
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
    $feature = Feature::factory()->for($project)->create();

    $this->actingAs($outsider)
        ->get(route('portal.projects.show', [$project->organization, $project]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('viewer.isMember', false)
            ->where('viewer.canCreateFeature', true)
            ->where('viewer.canManageFeatures', false),
        );

    $this->actingAs($outsider)
        ->get(route('portal.features.show', [$project->organization, $project, $feature]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('viewer.isMember', false)
            ->where('viewer.canConvert', false)
            ->where('viewer.canCommentInternally', false)
            ->where('issue', null),
        );
});
