<?php

use App\Mcp\Servers\QuillServer;
use App\Mcp\Tools\Addresses\AddAddressTool;
use App\Mcp\Tools\Addresses\ListAddressesTool;
use App\Mcp\Tools\Addresses\RemoveAddressTool;
use App\Mcp\Tools\Addresses\UpdateAddressTool;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Organization;
use App\Models\User;

function addressToolOwner(): array
{
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $organization->addMember($user, 'Owner');
    $user->switchOrganization($organization);

    return [$user, $organization, Client::factory()->for($organization)->create()];
}

test('it adds, lists, updates and removes a contact address', function () {
    [$user, , $client] = addressToolOwner();
    $contact = ClientContact::factory()->for($client)->create();
    $owner = ['owner' => 'contact', 'client' => $client->slug, 'contact_id' => $contact->id];

    QuillServer::actingAs($user)->tool(AddAddressTool::class, [...$owner, 'type' => 'office', 'line1' => '2 Side St', 'city' => 'Tel Aviv'])->assertOk();
    $address = $contact->addresses()->sole();

    QuillServer::actingAs($user)->tool(ListAddressesTool::class, $owner)->assertOk()->assertSee('2 Side St');
    QuillServer::actingAs($user)->tool(UpdateAddressTool::class, [...$owner, 'address_id' => $address->id, 'country' => 'Israel'])->assertOk();

    expect($address->fresh())->line1->toBe('2 Side St')->country->toBe('Israel');

    QuillServer::actingAs($user)->tool(RemoveAddressTool::class, [...$owner, 'address_id' => $address->id])->assertOk();
    expect($contact->addresses()->exists())->toBeFalse();
});

test('it adds the organization\'s billing address', function () {
    [$user, $organization] = addressToolOwner();

    QuillServer::actingAs($user)->tool(AddAddressTool::class, ['owner' => 'organization', 'type' => 'billing', 'line1' => '1 Main St', 'city' => 'Austin'])->assertOk();

    expect($organization->billingAddress()->line1)->toBe('1 Main St');
});

test('an address of another owner cannot be changed through this one', function () {
    [$user, , $client] = addressToolOwner();
    $address = Client::factory()->for($client->organization)->create()->addresses()->create(['type' => 'billing', 'line1' => '1 Main St', 'city' => 'Austin']);

    QuillServer::actingAs($user)->tool(RemoveAddressTool::class, ['owner' => 'client', 'client' => $client->slug, 'address_id' => $address->id])->assertHasErrors();

    expect($address->fresh())->not->toBeNull();
});

test('members who cannot manage clients cannot add client addresses', function () {
    [, $organization, $client] = addressToolOwner();
    $member = User::factory()->create();
    $organization->addMember($member, 'Member');
    $member->switchOrganization($organization);

    QuillServer::actingAs($member)->tool(AddAddressTool::class, ['owner' => 'client', 'client' => $client->slug, 'type' => 'billing', 'line1' => '1 Main St', 'city' => 'Austin'])->assertHasErrors();

    expect($client->addresses()->exists())->toBeFalse();
});
