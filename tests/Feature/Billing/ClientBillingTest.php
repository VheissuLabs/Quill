<?php

use App\Enums\OrganizationPermission;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;

function clientBillingManager(): array
{
    $organization = Organization::factory()->create();
    $admin = User::factory()->create();
    $organization->addMember($admin, 'Admin');

    return [$organization, $admin, Client::factory()->for($organization)->create()];
}

test('a manager sets a client\'s billing rate, contact and billed-through client', function () {
    [$organization, $admin, $client] = clientBillingManager();
    $contact = ClientContact::factory()->for($client)->create();
    $payer = Client::factory()->for($organization)->create();

    $this->actingAs($admin)
        ->patch(route('clients.update', ['current_organization' => $organization, 'client' => $client]), [
            'name' => $client->name,
            'billing_rate' => '125',
            'billing_contact_id' => $contact->id,
            'billed_through_client_id' => $payer->id,
        ])
        ->assertSessionHasNoErrors();

    expect($client->fresh())
        ->billing_rate->toBe('125.00')
        ->billing_contact_id->toBe($contact->id)
        ->billed_through_client_id->toBe($payer->id);
});

test('the client edit page offers billing options to a manager', function () {
    [$organization, $admin, $client] = clientBillingManager();
    $contact = ClientContact::factory()->for($client)->create();
    $other = Client::factory()->for($organization)->create();

    $this->actingAs($admin)
        ->get(route('clients.edit', ['current_organization' => $organization, 'client' => $client]))
        ->assertInertia(fn ($page) => $page
            ->component('clients/Edit')
            ->where('billing.contacts.0.id', $contact->id)
            ->has('billing.billedThroughOptions', 1)
            ->where('billing.billedThroughOptions.0.id', $other->id));
});

test('billing contact must belong to the client', function () {
    [$organization, $admin, $client] = clientBillingManager();
    $stranger = ClientContact::factory()->for(Client::factory()->for($organization))->create();

    $this->actingAs($admin)
        ->patch(route('clients.update', ['current_organization' => $organization, 'client' => $client]), [
            'name' => $client->name,
            'billing_contact_id' => $stranger->id,
        ])
        ->assertSessionHasErrors('billing_contact_id');
});

test('billed-through client must be in the workspace and not the client itself', function () {
    [$organization, $admin, $client] = clientBillingManager();
    $foreign = Client::factory()->create();
    $route = route('clients.update', ['current_organization' => $organization, 'client' => $client]);

    $this->actingAs($admin)
        ->patch($route, ['name' => $client->name, 'billed_through_client_id' => $foreign->id])
        ->assertSessionHasErrors('billed_through_client_id');

    $this->actingAs($admin)
        ->patch($route, ['name' => $client->name, 'billed_through_client_id' => $client->id])
        ->assertSessionHasErrors('billed_through_client_id');
});

test('billing fields are ignored for members without invoice management', function () {
    $organization = Organization::factory()->create();
    $role = $organization->roles()->create(['name' => 'Account lead', 'guard_name' => 'web']);
    $role->syncPermissions([OrganizationPermission::ManageClients->value]);
    $lead = User::factory()->create();
    $organization->addMember($lead, $role);
    $client = Client::factory()->for($organization)->create();

    $this->actingAs($lead)
        ->patch(route('clients.update', ['current_organization' => $organization, 'client' => $client]), [
            'name' => 'Renamed',
            'billing_rate' => '99',
        ])
        ->assertSessionHasNoErrors();

    expect($client->fresh())->name->toBe('Renamed')->billing_rate->toBeNull();
});

test('92 Labs bills KMS Tools work', function () {
    $organization = Organization::factory()->create();
    $labs = Client::factory()->for($organization)->create();
    $kms = Client::factory()->for($organization)->create(['billed_through_client_id' => $labs->id]);
    Client::factory()->for($organization)->create();

    expect($labs->billableClientIds()->all())->toEqual([$labs->id, $kms->id])
        ->and($kms->billableClientIds()->all())->toEqual([$kms->id]);
});

test('project rate falls back to client then workspace default', function () {
    $organization = Organization::factory()->create();
    $organization->billingSettings()->update(['default_rate' => '100']);
    $client = Client::factory()->for($organization)->create(['billing_rate' => '125']);
    $project = Project::factory()->for($organization)->create(['client_id' => $client->id, 'billing_rate' => '150']);

    expect($project->billingRate())->toBe('150.00');

    $project->update(['billing_rate' => null]);
    expect($project->fresh()->billingRate())->toBe('125.00');

    $client->update(['billing_rate' => null]);
    expect($project->fresh()->billingRate())->toBe('100.00');

    $organization->billingSettings()->update(['default_rate' => null]);
    expect($project->fresh()->billingRate())->toBeNull();
});

test('a manager sets a project billing rate', function () {
    [$organization, $admin] = clientBillingManager();
    $project = Project::factory()->for($organization)->create();

    $this->actingAs($admin)
        ->patch(route('projects.update', ['current_organization' => $organization, 'project' => $project]), [
            'name' => $project->name,
            'visibility' => 'private',
            'billing_rate' => '175',
        ])
        ->assertSessionHasNoErrors();

    expect($project->fresh()->billing_rate)->toBe('175.00');
});

test('a client cannot be billed through a client that is billed through it', function () {
    [$organization, $admin, $a] = clientBillingManager();
    $b = Client::factory()->for($organization)->create(['billed_through_client_id' => $a->id]);

    $this->actingAs($admin)
        ->patch(route('clients.update', ['current_organization' => $organization, 'client' => $a]), [
            'name' => $a->name,
            'billed_through_client_id' => $b->id,
        ])
        ->assertSessionHasErrors('billed_through_client_id');
});

test('billed-through chains are rejected in both directions', function () {
    [$organization, $admin, $a] = clientBillingManager();
    $b = Client::factory()->for($organization)->create();
    $c = Client::factory()->for($organization)->create();
    $b->update(['billed_through_client_id' => $c->id]);

    $this->actingAs($admin)
        ->patch(route('clients.update', ['current_organization' => $organization, 'client' => $a]), [
            'name' => $a->name,
            'billed_through_client_id' => $b->id,
        ])
        ->assertSessionHasErrors('billed_through_client_id');

    $this->actingAs($admin)
        ->patch(route('clients.update', ['current_organization' => $organization, 'client' => $c]), [
            'name' => $c->name,
            'billed_through_client_id' => $a->id,
        ])
        ->assertSessionHasErrors('billed_through_client_id');

    expect($a->fresh()->billed_through_client_id)->toBeNull()
        ->and($c->fresh()->billed_through_client_id)->toBeNull();
});
