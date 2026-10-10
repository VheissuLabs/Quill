<?php

use App\Enums\ProjectVisibility;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function overviewClient(): array
{
    $organization = Organization::factory()->create();
    config(['services.resend.support_organization' => $organization->slug]);

    return [$organization, Client::factory()->for($organization)->create()];
}

test('a client overview lists its contacts, projects and recent tickets', function () {
    [$organization, $client] = overviewClient();
    $admin = User::factory()->create();
    $organization->addMember($admin, 'Admin');
    ClientContact::factory()->for($client)->create(['name' => 'Jane Doe']);
    Project::factory()->for($organization)->public()->create(['client_id' => $client->id, 'name' => 'Website']);
    Ticket::factory()->forClient($client)->create();

    $this->actingAs($admin)
        ->get(route('clients.show', ['current_organization' => $organization, 'client' => $client]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('clients/Show')
            ->where('client.contacts.0.name', 'Jane Doe')
            ->where('projects.0.name', 'Website')
            ->has('tickets', 1));
});

test('members see the overview without tickets or private projects of teams they are not on', function () {
    [$organization, $client] = overviewClient();
    $member = User::factory()->create();
    $organization->addMember($member, 'Member');
    Project::factory()->for($organization)->create(['client_id' => $client->id, 'visibility' => ProjectVisibility::Private])
        ->teams()->attach(Team::factory()->for($organization)->create());
    Ticket::factory()->forClient($client)->create();

    $this->actingAs($member)
        ->get(route('clients.show', ['current_organization' => $organization, 'client' => $client]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('projects', 0)
            ->has('tickets', 0));
});

test('a client from another organization is not found', function () {
    [$organization] = overviewClient();
    $member = User::factory()->create();
    $organization->addMember($member, 'Member');

    $this->actingAs($member)
        ->get(route('clients.show', ['current_organization' => $organization, 'client' => Client::factory()->create()]))
        ->assertNotFound();
});
