<?php

use App\Enums\ProjectVisibility;
use App\Models\Client;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the projects index lists the organization projects the user may see', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');

    $visible = Project::factory()->for($organization)->create(['name' => 'Visible']);
    $restricted = Project::factory()->for($organization)->create(['name' => 'Restricted']);
    $restricted->teams()->attach(Team::factory()->for($organization)->create());
    Project::factory()->create(['name' => 'Other Organization']);

    $response = $this
        ->actingAs($member)
        ->get(route('projects.index', ['current_organization' => $organization]));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('projects/Index')
            ->has('projects', 1)
            ->where('projects.0.slug', $visible->slug),
        );
});

test('projects can be created by admins', function () {
    $admin = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($admin, 'Admin');
    $client = Client::factory()->for($organization)->create();
    $team = Team::factory()->for($organization)->create();

    $response = $this
        ->actingAs($admin)
        ->post(route('projects.store', ['current_organization' => $organization]), [
            'name' => 'GitDifferent',
            'description' => 'Git, but different.',
            'visibility' => ProjectVisibility::Private->value,
            'client_id' => $client->id,
            'team_ids' => [$team->id],
        ]);

    $project = Project::firstWhere('name', 'GitDifferent');

    $response->assertRedirect(route('projects.edit', ['current_organization' => $organization, 'project' => $project]));

    expect($project)
        ->organization_id->toBe($organization->id)
        ->client_id->toBe($client->id)
        ->visibility->toBe(ProjectVisibility::Private);
    expect($project->teams->pluck('id')->all())->toBe([$team->id]);
});

test('public projects are never restricted to teams', function () {
    $admin = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($admin, 'Admin');
    $team = Team::factory()->for($organization)->create();

    $this
        ->actingAs($admin)
        ->post(route('projects.store', ['current_organization' => $organization]), [
            'name' => 'Public Project',
            'visibility' => ProjectVisibility::Public->value,
            'team_ids' => [$team->id],
        ]);

    expect(Project::firstWhere('name', 'Public Project')->teams)->toBeEmpty();
});

test('projects cannot be created by members', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');

    $response = $this
        ->actingAs($member)
        ->post(route('projects.store', ['current_organization' => $organization]), [
            'name' => 'Nope',
            'visibility' => ProjectVisibility::Private->value,
        ]);

    $response->assertForbidden();
});

test('project creation rejects clients and teams from another organization', function () {
    $admin = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($admin, 'Admin');
    $foreignClient = Client::factory()->create();
    $foreignTeam = Team::factory()->create();

    $response = $this
        ->actingAs($admin)
        ->post(route('projects.store', ['current_organization' => $organization]), [
            'name' => 'Leaky',
            'visibility' => ProjectVisibility::Private->value,
            'client_id' => $foreignClient->id,
            'team_ids' => [$foreignTeam->id],
        ]);

    $response->assertSessionHasErrors(['client_id', 'team_ids.0']);
});

test('projects can be updated by admins', function () {
    $admin = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($admin, 'Admin');
    $project = Project::factory()->for($organization)->create(['name' => 'Old Name']);

    $response = $this
        ->actingAs($admin)
        ->patch(route('projects.update', ['current_organization' => $organization, 'project' => $project]), [
            'name' => 'New Name',
            'visibility' => ProjectVisibility::Public->value,
        ]);

    $response->assertRedirect(route('projects.edit', ['current_organization' => $organization, 'project' => $project->fresh()]));

    expect($project->fresh())
        ->name->toBe('New Name')
        ->slug->toBe('new-name')
        ->visibility->toBe(ProjectVisibility::Public);
});

test('projects of another organization cannot be reached through the current organization', function () {
    $admin = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($admin, 'Admin');
    $foreignProject = Project::factory()->create();

    $response = $this
        ->actingAs($admin)
        ->get(route('projects.edit', ['current_organization' => $organization, 'project' => $foreignProject]));

    $response->assertNotFound();
});

test('projects can be deleted by admins', function () {
    $admin = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($admin, 'Admin');
    $project = Project::factory()->for($organization)->create();

    $response = $this
        ->actingAs($admin)
        ->delete(route('projects.destroy', ['current_organization' => $organization, 'project' => $project]));

    $response->assertRedirect(route('projects.index', ['current_organization' => $organization]));

    $this->assertModelMissing($project);
});

test('project slugs are unique within an organization only', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    $first = Project::factory()->for($organization)->create(['name' => 'Acme']);
    $second = Project::factory()->for($organization)->create(['name' => 'Acme']);
    $elsewhere = Project::factory()->for($otherOrganization)->create(['name' => 'Acme']);

    expect($first->slug)->toBe('acme');
    expect($second->slug)->toBe('acme-1');
    expect($elsewhere->slug)->toBe('acme');
});

test('projects survive feedback voting being switched off', function () {
    $manager = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($manager, 'Owner');
    $organization->updateModuleSettings([
        'feedback_enabled' => false,
        'feedback_boards_enabled' => false,
        'feedback_voting_enabled' => false,
        'development_enabled' => true,
        'development_issues_enabled' => true,
        'development_sprints_enabled' => true,
        'development_workflow_enabled' => true,
        'teams_enabled' => true,
    ]);

    $project = Project::factory()->for($organization)->create(['name' => 'Internal work']);

    $this->actingAs($manager)
        ->get(route('projects.index', ['current_organization' => $organization]))
        ->assertOk();

    $this->actingAs($manager)
        ->post(route('projects.store', ['current_organization' => $organization]), [
            'name' => 'Another',
            'visibility' => ProjectVisibility::Private->value,
        ])
        ->assertRedirect();

    $this->actingAs($manager)
        ->patch(route('projects.update', ['current_organization' => $organization, 'project' => $project]), [
            'name' => 'Renamed',
            'visibility' => ProjectVisibility::Private->value,
        ])
        ->assertRedirect();

    expect($project->fresh()->name)->toBe('Renamed');
});

test('the public feedback board still goes away when feedback voting is switched off', function () {
    $organization = Organization::factory()->create();
    $organization->updateModuleSettings([
        'feedback_enabled' => false,
        'feedback_boards_enabled' => false,
        'feedback_voting_enabled' => false,
        'development_enabled' => true,
        'development_issues_enabled' => true,
        'development_sprints_enabled' => true,
        'development_workflow_enabled' => true,
        'teams_enabled' => true,
    ]);

    $project = Project::factory()->for($organization)->create([
        'visibility' => ProjectVisibility::Public,
    ]);

    $this->get(route('portal.projects.show', ['organization' => $organization, 'project' => $project]))
        ->assertForbidden();
});
