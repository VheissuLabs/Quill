<?php

use App\Models\Client;
use App\Models\ClientDomain;
use App\Models\Organization;
use App\Models\User;

function clientManager(): array
{
    $organization = Organization::factory()->create();
    $admin = User::factory()->create();
    $organization->addMember($admin, 'Admin');

    return [$organization, $admin, Client::factory()->for($organization)->create()];
}

test('a client manager adds a support domain to a client', function () {
    [$organization, $admin, $client] = clientManager();

    $this->actingAs($admin)
        ->post(route('clients.domains.store', ['current_organization' => $organization, 'client' => $client]), ['domain' => ' @Acme.COM '])
        ->assertRedirect();

    expect($client->domains()->sole())
        ->domain->toBe('acme.com')
        ->organization_id->toBe($organization->id);
});

test('free mail domains are refused because anyone can send from them', function () {
    [$organization, $admin, $client] = clientManager();

    $this->actingAs($admin)
        ->post(route('clients.domains.store', ['current_organization' => $organization, 'client' => $client]), ['domain' => 'gmail.com'])
        ->assertSessionHasErrors(['domain' => 'Anyone can send from gmail.com, so it cannot identify a client.']);
});

test('a domain already owned by another client is refused', function () {
    [$organization, $admin, $client] = clientManager();
    ClientDomain::factory()->for(Client::factory()->for($organization))->create(['domain' => 'acme.com']);

    $this->actingAs($admin)
        ->post(route('clients.domains.store', ['current_organization' => $organization, 'client' => $client]), ['domain' => 'acme.com'])
        ->assertSessionHasErrors(['domain' => 'Another client already uses that domain.']);
});

test('members without client management cannot add domains', function () {
    [$organization, , $client] = clientManager();
    $member = User::factory()->create();
    $organization->addMember($member, 'Member');

    $this->actingAs($member)
        ->post(route('clients.domains.store', ['current_organization' => $organization, 'client' => $client]), ['domain' => 'acme.com'])
        ->assertForbidden();
});

test('a client manager removes a domain', function () {
    [$organization, $admin, $client] = clientManager();
    $domain = ClientDomain::factory()->for($client)->create();

    $this->actingAs($admin)
        ->delete(route('clients.domains.destroy', ['current_organization' => $organization, 'client' => $client, 'domain' => $domain]))
        ->assertRedirect();

    expect($client->domains()->count())->toBe(0);
});
