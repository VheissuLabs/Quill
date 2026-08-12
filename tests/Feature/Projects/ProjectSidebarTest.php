<?php

use App\Enums\OrganizationRole;
use App\Models\Client;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;

test('the sidebar receives the organization projects on every page', function () {
    [$organization, $user] = organizationWith();
    $client = Client::factory()->heldBy($organization)->create(['name' => 'Acme Title']);
    $team = Team::factory()->heldBy($organization)->create(['name' => 'Delivery']);

    Project::factory()->ownedBy($client)->create(['name' => 'Acme Website']);
    Project::factory()->ownedBy($team)->create(['name' => 'Harbor Rebuild']);

    $this->actingAs($user)
        ->get(route('dashboard', ['current_team' => $user->currentTeam?->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('projects', 2)
            ->where('projects.0.name', 'Acme Website')
            ->where('projects.0.ownerName', 'Acme Title')
            ->where('projects.0.ownerType', 'client')
            ->where('projects.1.ownerType', 'team'),
        );
});

test('projects from another organization are never shared', function () {
    [$mine, $user] = organizationWith();
    $theirs = Organization::factory()->create(['name' => '92 Labs']);

    Project::factory()->ownedBy(Client::factory()->heldBy($theirs)->create())->create(['name' => 'Theirs']);

    $this->actingAs($user)
        ->get(route('dashboard', ['current_team' => $user->currentTeam?->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('projects', []));
});

test('the shared list follows the organization the user switches to', function () {
    [$first, $user] = organizationWith();
    $second = Organization::factory()->create(['name' => '92 Labs']);

    $second->members()->attach($user);
    $user->assignOrganizationRole($second, OrganizationRole::Member);

    Project::factory()->ownedBy(Client::factory()->heldBy($second)->create())->create(['name' => 'Second Org Project']);

    expect($user->toUserProjects()->pluck('name')->all())->toBe([]);

    $user->switchOrganization($second);

    expect($user->refresh()->toUserProjects()->pluck('name')->all())->toBe(['Second Org Project']);
});

test('a removed member is shared no projects', function () {
    [$organization, $user] = organizationWith();

    Project::factory()->ownedBy(Client::factory()->heldBy($organization)->create())->create(['name' => 'Acme Website']);

    $organization->members()->detach($user);

    expect($user->refresh()->toUserProjects())->toBeEmpty();
})->note('current_organization_id is not cleared when a membership is revoked.');

test('a project page renders its name and owner', function () {
    [$organization, $user] = organizationWith();
    $client = Client::factory()->heldBy($organization)->create(['name' => 'Acme Title']);

    $project = Project::factory()->ownedBy($client)->create([
        'name' => 'Acme Website',
        'description' => 'The public marketing site.',
    ]);

    $client->update(['default_project_id' => $project->id]);

    $this->actingAs($user)
        ->get(route('projects.show', ['project' => $project->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('projects/Show')
            ->where('project.name', 'Acme Website')
            ->where('project.ownerName', 'Acme Title')
            ->where('project.description', 'The public marketing site.')
            ->where('project.defaultForClients', ['Acme Title']),
        );
});

test('a project in another organization is a not found', function () {
    [, $user] = organizationWith();
    $theirs = Organization::factory()->create(['name' => '92 Labs']);

    $project = Project::factory()
        ->ownedBy(Client::factory()->heldBy($theirs)->create())
        ->create(['name' => 'Theirs']);

    $this->actingAs($user)
        ->get(route('projects.show', ['project' => $project->slug]))
        ->assertNotFound();
})->note('Slugs are unique across the table, so another tenant\'s project resolves and must be refused.');

test('a guest cannot open a project', function () {
    [$organization] = organizationWith();

    $project = Project::factory()
        ->ownedBy(Client::factory()->heldBy($organization)->create())
        ->create(['name' => 'Acme Website']);

    $this->get(route('projects.show', ['project' => $project->slug]))
        ->assertRedirect(route('login'));
});

test('a client contact cannot open a project', function () {
    [$organization] = organizationWith();
    $client = Client::factory()->heldBy($organization)->create(['name' => 'Acme Title']);
    $project = Project::factory()->ownedBy($client)->create(['name' => 'Acme Website']);

    $contact = contactFor($client, 'Lucy Client');
    $contact->switchOrganization($organization);

    $this->actingAs($contact->refresh())
        ->get(route('projects.show', ['project' => $project->slug]))
        ->assertForbidden();
})->note('Projects are internal structure; a contact never picks one.');

test('a user with no organization is shared no projects', function () {
    $stranger = User::factory()->create(['current_organization_id' => null]);

    expect($stranger->toUserProjects())->toBeEmpty();
});
