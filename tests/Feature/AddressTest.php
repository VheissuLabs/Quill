<?php

use App\Models\Address;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Organization;
use App\Models\User;

function addressManager(): array
{
    $organization = Organization::factory()->create();
    $admin = User::factory()->create();
    $organization->addMember($admin, 'Owner');

    return [$organization, $admin, Client::factory()->for($organization)->create()];
}

$street = ['type' => 'billing', 'line1' => '1 Main St', 'city' => 'Austin', 'region' => 'TX', 'postal_code' => '78701', 'country' => 'United States'];

test('a client manager adds, changes and removes a client address', function () use ($street) {
    [$organization, $admin, $client] = addressManager();
    $args = ['current_organization' => $organization, 'client' => $client];

    $this->actingAs($admin)->post(route('clients.addresses.store', $args), $street)->assertSessionHasNoErrors();
    $address = $client->addresses()->sole();

    $this->actingAs($admin)->patch(route('clients.addresses.update', [...$args, 'address' => $address]), [...$street, 'type' => 'mailing', 'line2' => 'Suite 5'])->assertSessionHasNoErrors();
    expect($address->fresh())->type->value->toBe('mailing')->line2->toBe('Suite 5');

    $this->actingAs($admin)->delete(route('clients.addresses.destroy', [...$args, 'address' => $address]))->assertRedirect();
    expect($client->addresses()->exists())->toBeFalse();
});

test('a contact and the organization each keep their own addresses', function () use ($street) {
    [$organization, $admin, $client] = addressManager();
    $contact = ClientContact::factory()->for($client)->create();

    $this->actingAs($admin)->post(route('clients.contacts.addresses.store', ['current_organization' => $organization, 'client' => $client, 'contact' => $contact]), $street)->assertSessionHasNoErrors();
    $this->actingAs($admin)->post(route('organizations.addresses.store', $organization), [...$street, 'type' => 'office'])->assertSessionHasNoErrors();

    expect($contact->addresses()->sole()->line1)->toBe('1 Main St')
        ->and($organization->addresses()->sole()->type->value)->toBe('office')
        ->and($client->addresses()->exists())->toBeFalse();
});

test('an address must have a type, street and city', function () {
    [$organization, $admin, $client] = addressManager();

    $this->actingAs($admin)
        ->post(route('clients.addresses.store', ['current_organization' => $organization, 'client' => $client]), ['type' => 'nowhere'])
        ->assertSessionHasErrors(['type', 'line1', 'city']);
});

test('another client\'s address cannot be changed through this client', function () use ($street) {
    [$organization, $admin, $client] = addressManager();
    $other = Address::factory()->create(['addressable_id' => Client::factory()->for($organization)]);

    $this->actingAs($admin)
        ->patch(route('clients.addresses.update', ['current_organization' => $organization, 'client' => $client, 'address' => $other]), $street)
        ->assertNotFound();
});

test('members who cannot update the organization cannot change its addresses', function () use ($street) {
    [$organization] = addressManager();
    $member = User::factory()->create();
    $organization->addMember($member, 'Member');

    $this->actingAs($member)->post(route('organizations.addresses.store', $organization), $street)->assertForbidden();

    expect($organization->addresses()->exists())->toBeFalse();
});

test('the contact page shows the contact with phone and addresses, and saves changes', function () {
    [$organization, $admin, $client] = addressManager();
    $contact = ClientContact::factory()->for($client)->create(['name' => 'Kfir Alfia', 'email' => 'kfir@acme.test']);
    $contact->addresses()->create(['type' => 'office', 'line1' => '2 Side St', 'city' => 'Tel Aviv']);
    $args = ['current_organization' => $organization, 'client' => $client, 'contact' => $contact];

    $this->actingAs($admin)->get(route('clients.contacts.show', $args))
        ->assertInertia(fn ($page) => $page
            ->component('clients/contacts/Show')
            ->where('contact.name', 'Kfir Alfia')
            ->where('contact.addresses.0.lines', ['2 Side St', 'Tel Aviv']));

    $this->actingAs($admin)->patch(route('clients.contacts.update', $args), ['name' => 'Kfir Alfia', 'title' => 'CTO', 'email' => 'kfir@acme.test', 'phone' => '+1 555 123 4567'])->assertSessionHasNoErrors();

    expect($contact->fresh())->title->toBe('CTO')->phone->toBe('+1 555 123 4567');
});

test('removing a contact from its page returns to the client', function () {
    [$organization, $admin, $client] = addressManager();
    $contact = ClientContact::factory()->for($client)->create();

    $this->actingAs($admin)
        ->delete(route('clients.contacts.destroy', ['current_organization' => $organization, 'client' => $client, 'contact' => $contact]))
        ->assertRedirect(route('clients.show', ['current_organization' => $organization, 'client' => $client]));
});
