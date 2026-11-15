<?php

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;

test('clients can be created by admins', function () {
    $admin = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($admin, 'Admin');

    $response = $this
        ->actingAs($admin)
        ->post(route('clients.store', ['current_organization' => $organization]), [
            'name' => 'Whitelabel Notary',
        ]);

    $response->assertRedirect(route('clients.index', ['current_organization' => $organization]));

    $this->assertDatabaseHas('clients', [
        'organization_id' => $organization->id,
        'name' => 'Whitelabel Notary',
        'slug' => 'whitelabel-notary',
    ]);
});

test('clients cannot be created by members', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');

    $response = $this
        ->actingAs($member)
        ->post(route('clients.store', ['current_organization' => $organization]), [
            'name' => 'Nope',
        ]);

    $response->assertForbidden();
});

test('clients can be renamed by admins', function () {
    $admin = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($admin, 'Admin');
    $client = Client::factory()->for($organization)->create();

    $response = $this
        ->actingAs($admin)
        ->patch(route('clients.update', ['current_organization' => $organization, 'client' => $client]), [
            'name' => 'Renamed',
        ]);

    $response->assertRedirect(route('clients.edit', ['current_organization' => $organization, 'client' => $client->fresh()]));

    expect($client->fresh()->name)->toBe('Renamed');
});

test('deleting a client keeps its projects', function () {
    $admin = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($admin, 'Admin');
    $client = Client::factory()->for($organization)->create();
    $project = Project::factory()->for($organization)->for($client)->create();

    $response = $this
        ->actingAs($admin)
        ->delete(route('clients.destroy', ['current_organization' => $organization, 'client' => $client]));

    $response->assertRedirect(route('clients.index', ['current_organization' => $organization]));

    $this->assertModelMissing($client);
    expect($project->fresh()->client_id)->toBeNull();
});

test('a client with invoices cannot be deleted', function () {
    $admin = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($admin, 'Admin');
    $client = Client::factory()->for($organization)->create();
    $invoice = Invoice::factory()->for($organization)->for($client)->create();

    $this->actingAs($admin)
        ->delete(route('clients.destroy', ['current_organization' => $organization, 'client' => $client]))
        ->assertForbidden();

    $this->assertModelExists($client);
    $this->assertModelExists($invoice);
});
