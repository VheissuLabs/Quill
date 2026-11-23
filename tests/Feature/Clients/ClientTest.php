<?php

use App\Models\Client;
use App\Models\ClientDomain;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

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

    $this->assertSoftDeleted($client);
    expect($project->fresh()->client_id)->toBeNull();
});

test('deleting a client keeps it and its invoices for the records', function () {
    $admin = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($admin, 'Admin');
    $client = Client::factory()->for($organization)->create();
    $sent = Invoice::factory()->for($organization)->for($client)->sent()->create();
    $draft = Invoice::factory()->for($organization)->for($client)->create();
    InvoicePayment::factory()->for($sent)->create();

    $this->actingAs($admin)
        ->delete(route('clients.destroy', ['current_organization' => $organization, 'client' => $client]))
        ->assertRedirect(route('clients.index', ['current_organization' => $organization]));

    $this->assertSoftDeleted($client);
    $this->assertModelExists($sent);
    $this->assertModelExists($draft);
    expect($sent->fresh()->client->name)->toBe($client->name);
});

test('deleting a client stops its billing and frees its domains', function () {
    $admin = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($admin, 'Admin');
    $client = Client::factory()->for($organization)->create(['billing_cycle' => 'monthly']);
    $billedThrough = Client::factory()->for($organization)->create(['billed_through_client_id' => $client->id]);
    $cycleInvoice = Invoice::factory()->for($organization)->for($client)->create(['closes_at' => now()->addDay()]);
    $domain = ClientDomain::factory()->for($client)->create(['organization_id' => $organization->id]);

    $this->actingAs($admin)
        ->delete(route('clients.destroy', ['current_organization' => $organization, 'client' => $client]));

    expect($client->fresh()->billing_cycle)->toBeNull()
        ->and($cycleInvoice->fresh()->closes_at)->toBeNull()
        ->and($billedThrough->fresh()->billed_through_client_id)->toBeNull();
    $this->assertModelMissing($domain);
});

test('a deleted client is gone from the client list and its name can be reused', function () {
    $admin = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($admin, 'Admin');
    $client = Client::factory()->for($organization)->create(['name' => 'Acme']);

    $client->deleteKeepingRecords();
    $replacement = Client::factory()->for($organization)->create(['name' => 'Acme']);

    expect($replacement->slug)->not->toBe($client->slug);

    $this->actingAs($admin)
        ->get(route('clients.index', ['current_organization' => $organization]))
        ->assertInertia(fn (Assert $page) => $page->has('clients', 1)->where('clients.0.id', $replacement->id));
});

test('a deleted client page still opens and shows when it was deleted', function () {
    $admin = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($admin, 'Admin');
    $client = Client::factory()->for($organization)->create();
    $client->deleteKeepingRecords();

    $this->actingAs($admin)
        ->get(route('clients.show', ['current_organization' => $organization, 'client' => $client]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->whereNot('client.deletedAt', null));
});

test('a deleted client cannot be changed', function () {
    $admin = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($admin, 'Admin');
    $client = Client::factory()->for($organization)->create();
    $client->deleteKeepingRecords();

    expect($admin->can('update', $client))->toBeFalse()
        ->and($admin->can('delete', $client))->toBeFalse();

    $this->actingAs($admin)
        ->get(route('clients.edit', ['current_organization' => $organization, 'client' => $client]))
        ->assertNotFound();
});

test('a deleted client can be restored by someone who manages clients', function () {
    $admin = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($admin, 'Admin');
    $client = Client::factory()->for($organization)->create();
    $client->deleteKeepingRecords();

    $this->actingAs($admin)
        ->post(route('clients.restore', ['current_organization' => $organization, 'client' => $client]))
        ->assertRedirect(route('clients.show', ['current_organization' => $organization, 'client' => $client]));

    $this->assertNotSoftDeleted($client);
});

test('members cannot restore a deleted client', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');
    $client = Client::factory()->for($organization)->create();
    $client->deleteKeepingRecords();

    $this->actingAs($member)
        ->post(route('clients.restore', ['current_organization' => $organization, 'client' => $client]))
        ->assertForbidden();

    $this->assertSoftDeleted($client);
});
