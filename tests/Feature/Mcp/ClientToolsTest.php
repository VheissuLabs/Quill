<?php

use App\Enums\BillingCycle;
use App\Mcp\Servers\QuillServer;
use App\Mcp\Tools\Clients\AddClientContactTool;
use App\Mcp\Tools\Clients\AddClientDomainTool;
use App\Mcp\Tools\Clients\CreateClientTool;
use App\Mcp\Tools\Clients\DeleteClientTool;
use App\Mcp\Tools\Clients\ListClientsTool;
use App\Mcp\Tools\Clients\RemoveClientContactTool;
use App\Mcp\Tools\Clients\RemoveClientDomainTool;
use App\Mcp\Tools\Clients\RestoreClientTool;
use App\Mcp\Tools\Clients\ShowClientTool;
use App\Mcp\Tools\Clients\UpdateClientContactTool;
use App\Mcp\Tools\Clients\UpdateClientTool;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientDomain;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;

function clientToolMember(string $role = 'Admin'): array
{
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $organization->addMember($user, $role);
    $user->switchOrganization($organization);

    return [$user, $organization, Client::factory()->for($organization)->create(['name' => 'Acme Corp'])];
}

test('it lists the organization\'s clients', function () {
    [$user] = clientToolMember('Member');
    Client::factory()->create(['name' => 'Foreign Client']);

    QuillServer::actingAs($user)->tool(ListClientsTool::class)
        ->assertOk()
        ->assertSee('Acme Corp')
        ->assertDontSee('Foreign Client');
});

test('it lists deleted clients on request', function () {
    [$user, $organization, $client] = clientToolMember();
    Client::factory()->for($organization)->create(['name' => 'Gone Inc'])->delete();

    QuillServer::actingAs($user)->tool(ListClientsTool::class, ['deleted' => true])
        ->assertOk()
        ->assertSee('Gone Inc')
        ->assertDontSee('Acme Corp');
});

test('it shows a client with contacts, domains, projects and invoices', function () {
    [$user, $organization, $client] = clientToolMember();
    ClientContact::factory()->for($client)->create(['email' => 'pat@acme.test']);
    ClientDomain::factory()->for($client)->create(['domain' => 'acme.test']);
    Project::factory()->for($organization)->create(['client_id' => $client->id, 'name' => 'Acme Site']);
    $invoice = Invoice::factory()->for($organization)->for($client)->create();

    QuillServer::actingAs($user)->tool(ShowClientTool::class, ['client' => $client->slug])
        ->assertOk()
        ->assertSee(['pat@acme.test', 'acme.test', 'Acme Site', $invoice->number]);
});

test('members see a client without its billing or invoices', function () {
    [$user, $organization, $client] = clientToolMember('Member');
    $invoice = Invoice::factory()->for($organization)->for($client)->create();

    QuillServer::actingAs($user)->tool(ShowClientTool::class, ['client' => $client->id])
        ->assertOk()
        ->assertSee('Acme Corp')
        ->assertDontSee($invoice->number);
});

test('it cannot show a client from another organization', function () {
    [$user] = clientToolMember();
    $foreign = Client::factory()->create();

    QuillServer::actingAs($user)->tool(ShowClientTool::class, ['client' => $foreign->id])->assertHasErrors();
});

test('it creates a client with billing', function () {
    [$user, $organization] = clientToolMember();

    QuillServer::actingAs($user)->tool(CreateClientTool::class, ['name' => 'Whitelabel Notary', 'billing_rate' => 120, 'payment_terms_days' => 14])
        ->assertOk()
        ->assertSee('whitelabel-notary');

    expect($organization->clients()->where('name', 'Whitelabel Notary')->sole())
        ->billing_rate->toBe('120.00')
        ->payment_terms_days->toBe(14);
});

test('members cannot create clients', function () {
    [$user, $organization] = clientToolMember('Member');

    QuillServer::actingAs($user)->tool(CreateClientTool::class, ['name' => 'Nope'])->assertHasErrors();

    expect($organization->clients()->where('name', 'Nope')->exists())->toBeFalse();
});

test('creating a client uses the web validation', function () {
    [$user] = clientToolMember();

    QuillServer::actingAs($user)->tool(CreateClientTool::class, ['name' => ''])->assertHasErrors(['name']);
});

test('it updates a client and starts its billing cycle', function () {
    $this->travelTo('2026-11-16 12:00:00');
    [$user, , $client] = clientToolMember();
    $contact = ClientContact::factory()->for($client)->create();

    QuillServer::actingAs($user)->tool(UpdateClientTool::class, [
        'client' => $client->slug,
        'billing_rate' => 90,
        'billing_contact_id' => $contact->id,
        'billing_cycle' => BillingCycle::Monthly->value,
    ])->assertOk();

    expect($client->fresh())
        ->name->toBe('Acme Corp')
        ->billing_rate->toBe('90.00')
        ->billing_contact_id->toBe($contact->id)
        ->billing_cycle->toBe(BillingCycle::Monthly)
        ->and($client->fresh()->openCycleInvoice())->not->toBeNull();
});

test('members cannot update clients', function () {
    [$user, , $client] = clientToolMember('Member');

    QuillServer::actingAs($user)->tool(UpdateClientTool::class, ['client' => $client->slug, 'name' => 'Renamed'])->assertHasErrors();

    expect($client->fresh()->name)->toBe('Acme Corp');
});

test('it deletes and restores a client', function () {
    [$user, $organization, $client] = clientToolMember();
    $project = Project::factory()->for($organization)->create(['client_id' => $client->id]);

    QuillServer::actingAs($user)->tool(DeleteClientTool::class, ['client' => $client->slug])->assertOk();

    expect($client->fresh()->trashed())->toBeTrue()
        ->and($project->fresh()->client_id)->toBeNull();

    QuillServer::actingAs($user)->tool(RestoreClientTool::class, ['client' => $client->slug])->assertOk();

    expect($client->fresh()->trashed())->toBeFalse();
});

test('members cannot delete or restore clients', function () {
    [$user, $organization, $client] = clientToolMember('Member');
    $deleted = Client::factory()->for($organization)->create();
    $deleted->delete();

    QuillServer::actingAs($user)->tool(DeleteClientTool::class, ['client' => $client->slug])->assertHasErrors();
    QuillServer::actingAs($user)->tool(RestoreClientTool::class, ['client' => $deleted->slug])->assertHasErrors();

    expect($client->fresh()->trashed())->toBeFalse()
        ->and($deleted->fresh()->trashed())->toBeTrue();
});

test('it adds, updates and removes client contacts', function () {
    [$user, , $client] = clientToolMember();

    QuillServer::actingAs($user)->tool(AddClientContactTool::class, ['client' => $client->slug, 'name' => 'Pat', 'email' => ' PAT@Acme.test '])->assertOk();

    $contact = $client->contacts()->sole();
    expect($contact->email)->toBe('pat@acme.test');

    QuillServer::actingAs($user)->tool(UpdateClientContactTool::class, ['client' => $client->slug, 'contact_id' => $contact->id, 'name' => 'Patricia'])->assertOk();

    expect($contact->fresh())->name->toBe('Patricia')->email->toBe('pat@acme.test');

    QuillServer::actingAs($user)->tool(RemoveClientContactTool::class, ['client' => $client->slug, 'contact_id' => $contact->id])->assertOk();

    expect($client->contacts()->exists())->toBeFalse();
});

test('contact emails must be unique in the organization', function () {
    [$user, $organization, $client] = clientToolMember();
    ClientContact::factory()->for(Client::factory()->for($organization))->create(['email' => 'pat@acme.test']);

    QuillServer::actingAs($user)->tool(AddClientContactTool::class, ['client' => $client->slug, 'name' => 'Pat', 'email' => 'pat@acme.test'])
        ->assertHasErrors(['That email already belongs to a contact.']);
});

test('members cannot manage client contacts', function () {
    [$user, , $client] = clientToolMember('Member');
    $contact = ClientContact::factory()->for($client)->create();

    QuillServer::actingAs($user)->tool(AddClientContactTool::class, ['client' => $client->slug, 'name' => 'Pat', 'email' => 'pat@acme.test'])->assertHasErrors();
    QuillServer::actingAs($user)->tool(RemoveClientContactTool::class, ['client' => $client->slug, 'contact_id' => $contact->id])->assertHasErrors();

    expect($client->contacts()->count())->toBe(1);
});

test('a contact must belong to the named client', function () {
    [$user, $organization, $client] = clientToolMember();
    $other = ClientContact::factory()->for(Client::factory()->for($organization))->create();

    QuillServer::actingAs($user)->tool(RemoveClientContactTool::class, ['client' => $client->slug, 'contact_id' => $other->id])->assertHasErrors();

    expect($other->fresh())->not->toBeNull();
});

test('it adds and removes client domains', function () {
    [$user, , $client] = clientToolMember();

    QuillServer::actingAs($user)->tool(AddClientDomainTool::class, ['client' => $client->slug, 'domain' => '@Acme.test'])->assertOk()->assertSee('acme.test');

    $domain = $client->domains()->sole();

    QuillServer::actingAs($user)->tool(RemoveClientDomainTool::class, ['client' => $client->slug, 'domain_id' => $domain->id])->assertOk();

    expect($client->domains()->exists())->toBeFalse();
});

test('free mail domains are refused', function () {
    [$user, , $client] = clientToolMember();

    QuillServer::actingAs($user)->tool(AddClientDomainTool::class, ['client' => $client->slug, 'domain' => 'gmail.com'])->assertHasErrors();

    expect($client->domains()->exists())->toBeFalse();
});

test('members cannot manage client domains', function () {
    [$user, , $client] = clientToolMember('Member');

    QuillServer::actingAs($user)->tool(AddClientDomainTool::class, ['client' => $client->slug, 'domain' => 'acme.test'])->assertHasErrors();

    expect($client->domains()->exists())->toBeFalse();
});
