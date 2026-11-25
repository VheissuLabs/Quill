<?php

use App\Mcp\Servers\QuillServer;
use App\Mcp\Tools\Billing\ListTimeEntriesTool;
use App\Mcp\Tools\Billing\ShowInvoiceTool;
use App\Mcp\Tools\Billing\TrackTimeTool;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\TimeEntry;
use App\Models\User;

function timeEntriesOwner(): User
{
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $organization->addMember($user, 'Owner');
    $user->switchOrganization($organization);

    return $user;
}

test('unbilled entries can be listed for a client', function () {
    $user = timeEntriesOwner();
    $client = Client::factory()->for($user->currentOrganization)->create();
    $other = Client::factory()->for($user->currentOrganization)->create();
    $line = Invoice::factory()->for($user->currentOrganization)->for($client)->create()->lines()->create(['description' => 'Billed', 'quantity' => 1, 'rate' => 100]);
    TimeEntry::factory()->for($client)->create(['description' => 'Billed work', 'invoice_line_id' => $line->id]);
    TimeEntry::factory()->for($client)->create(['description' => 'Missed work', 'hours' => '1.50']);
    TimeEntry::factory()->for($other)->create(['description' => 'Other client work']);

    QuillServer::actingAs($user)->tool(ListTimeEntriesTool::class, ['client' => $client->slug, 'unbilled' => true])
        ->assertOk()
        ->assertSee(['Missed work', '"totalHours":1.5'])
        ->assertDontSee(['Billed work', 'Other client work']);
});

test('entries from another organization are never listed', function () {
    $user = timeEntriesOwner();
    TimeEntry::factory()->create(['description' => 'Someone else\'s work']);

    QuillServer::actingAs($user)->tool(ListTimeEntriesTool::class)
        ->assertOk()
        ->assertDontSee('Someone else');
});

test('an invoice shows the dated entries behind each line', function () {
    $this->travelTo('2026-11-25 12:00:00');
    $user = timeEntriesOwner();
    $client = Client::factory()->for($user->currentOrganization)->create(['billing_rate' => 100]);
    QuillServer::actingAs($user)->tool(TrackTimeTool::class, ['client' => $client->slug, 'description' => 'Daily Stand Up', 'hours' => 0.25, 'date' => '2026-11-24']);
    QuillServer::actingAs($user)->tool(TrackTimeTool::class, ['client' => $client->slug, 'description' => 'Daily Stand Up', 'hours' => 0.25]);

    QuillServer::actingAs($user)->tool(ShowInvoiceTool::class, ['invoice_id' => $client->invoices()->sole()->id])
        ->assertOk()
        ->assertSee(['"workedOn":"2026-11-24"', '"workedOn":"2026-11-25"']);
});
