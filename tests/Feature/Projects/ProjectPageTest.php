<?php

use App\Models\Client;
use App\Models\Organization;
use App\Models\Project;

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
