<?php

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Organization;
use App\Models\User;

function contactManager(): array
{
    $organization = Organization::factory()->create();
    config(['services.resend.support_organization' => $organization->slug]);
    $admin = User::factory()->create();
    $organization->addMember($admin, 'Admin');

    return [$organization, $admin, Client::factory()->for($organization)->create()];
}

test('a client manager adds a contact to a client', function () {
    [$organization, $admin, $client] = contactManager();

    $this->actingAs($admin)
        ->post(route('clients.contacts.store', ['current_organization' => $organization, 'client' => $client]), [
            'name' => 'Jane Doe',
            'title' => 'CTO',
            'email' => ' Jane@Acme.test ',
        ])
        ->assertRedirect();

    expect($client->contacts()->sole())
        ->name->toBe('Jane Doe')
        ->title->toBe('CTO')
        ->email->toBe('jane@acme.test')
        ->organization_id->toBe($organization->id);
});

test('an email can belong to only one contact in the organization', function () {
    [$organization, $admin, $client] = contactManager();
    ClientContact::factory()->for(Client::factory()->for($organization))->create(['email' => 'jane@acme.test']);

    $this->actingAs($admin)
        ->post(route('clients.contacts.store', ['current_organization' => $organization, 'client' => $client]), [
            'name' => 'Jane Doe',
            'email' => 'jane@acme.test',
        ])
        ->assertSessionHasErrors(['email' => 'That email already belongs to a contact.']);
});

test('a contact keeps its own email when renamed', function () {
    [$organization, $admin, $client] = contactManager();
    $contact = ClientContact::factory()->for($client)->create(['email' => 'jane@acme.test']);

    $this->actingAs($admin)
        ->patch(route('clients.contacts.update', ['current_organization' => $organization, 'client' => $client, 'contact' => $contact]), [
            'name' => 'Jane Smith',
            'email' => 'jane@acme.test',
        ])
        ->assertSessionHasNoErrors();

    expect($contact->fresh()->name)->toBe('Jane Smith');
});

test('a contact\'s title can be changed and cleared', function () {
    [$organization, $admin, $client] = contactManager();
    $contact = ClientContact::factory()->for($client)->create(['title' => 'Engineer', 'email' => 'jane@acme.test']);
    $route = route('clients.contacts.update', ['current_organization' => $organization, 'client' => $client, 'contact' => $contact]);

    $this->actingAs($admin)->patch($route, ['name' => 'Jane', 'title' => 'CTO', 'email' => 'jane@acme.test'])->assertSessionHasNoErrors();
    expect($contact->fresh()->title)->toBe('CTO');

    $this->actingAs($admin)->patch($route, ['name' => 'Jane', 'title' => '', 'email' => 'jane@acme.test'])->assertSessionHasNoErrors();
    expect($contact->fresh()->title)->toBeNull();
});

test('members without client management cannot add contacts', function () {
    [$organization, , $client] = contactManager();
    $member = User::factory()->create();
    $organization->addMember($member, 'Member');

    $this->actingAs($member)
        ->post(route('clients.contacts.store', ['current_organization' => $organization, 'client' => $client]), [
            'name' => 'Jane Doe',
            'email' => 'jane@acme.test',
        ])
        ->assertForbidden();
});

test('the contact lookup finds contacts by name or email with their client', function () {
    [$organization, $admin, $client] = contactManager();
    $jane = ClientContact::factory()->for($client)->create(['name' => 'Jane Doe', 'title' => 'CTO', 'email' => 'jane@acme.test']);
    ClientContact::factory()->for($client)->create(['name' => 'Bob Roe', 'email' => 'bob@acme.test']);
    ClientContact::factory()->create(['name' => 'Jane Elsewhere']);

    $this->actingAs($admin)
        ->getJson(route('client-contacts.index', ['current_organization' => $organization, 'q' => 'jane']))
        ->assertOk()
        ->assertExactJson([[
            'id' => $jane->id,
            'name' => 'Jane Doe',
            'title' => 'CTO',
            'email' => 'jane@acme.test',
            'phone' => null,
            'client' => ['id' => $client->id, 'name' => $client->name, 'slug' => $client->slug],
        ]]);
});
