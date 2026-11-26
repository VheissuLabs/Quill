<?php

use App\Enums\BillingCycle;
use App\Mcp\Servers\QuillServer;
use App\Mcp\Tools\Billing\LogTimeTool;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;

function timeTrackingSetup(array $client = []): array
{
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $organization->addMember($user, 'Owner');
    $user->switchOrganization($organization);

    $client = Client::factory()->for($organization)->create(['billing_rate' => 100, ...$client]);
    $project = Project::factory()->for($organization)->create(['client_id' => $client->id]);

    return [$user, Issue::factory()->for($project)->create(['title' => 'Build the login page']), $client];
}

test('logged hours land on the open cycle invoice as a line for the issue', function () {
    $this->travelTo('2026-11-16 12:00:00');
    [$user, $issue, $client] = timeTrackingSetup(['billing_cycle' => BillingCycle::Biweekly, 'billing_cycle_starts_on' => '2026-11-14']);

    QuillServer::actingAs($user)->tool(LogTimeTool::class, ['issue_id' => $issue->id, 'hours' => 1.5])->assertOk();

    $invoice = $client->openCycleInvoice();

    expect($invoice->lines)->toHaveCount(1)
        ->and($invoice->lines->first())->issue_id->toBe($issue->id)->description->toBe('Build the login page')->quantity->toBe('1.50')->amount->toBe('150.00')
        ->and($invoice->total)->toBe('150.00');
});

test('logging again adds to the same line', function () {
    [$user, $issue, $client] = timeTrackingSetup();

    QuillServer::actingAs($user)->tool(LogTimeTool::class, ['issue_id' => $issue->id, 'hours' => 2]);
    QuillServer::actingAs($user)->tool(LogTimeTool::class, ['issue_id' => $issue->id, 'hours' => 0.75])->assertOk();

    $invoice = $client->invoices()->sole();

    expect($invoice->lines()->sole()->quantity)->toBe('2.75')
        ->and($invoice->total)->toBe('275.00');
});

test('time goes on the draft of the client a project is billed through', function () {
    [$user, $issue, $client] = timeTrackingSetup();
    $parent = Client::factory()->for($client->organization)->create(['billing_rate' => 80]);
    $client->update(['billed_through_client_id' => $parent->id, 'billing_rate' => null]);

    QuillServer::actingAs($user)->tool(LogTimeTool::class, ['issue_id' => $issue->id, 'hours' => 1])->assertOk();

    expect($parent->invoices()->sole()->lines()->sole()->rate)->toBe('80.00')
        ->and($client->invoices()->exists())->toBeFalse();
});

test('sent invoices never get more time', function () {
    [$user, $issue, $client] = timeTrackingSetup();
    $sent = Invoice::factory()->for($client->organization)->for($client)->create(['sent_at' => now()]);

    QuillServer::actingAs($user)->tool(LogTimeTool::class, ['issue_id' => $issue->id, 'hours' => 1])->assertOk();

    expect($sent->lines()->exists())->toBeFalse()
        ->and($client->invoices()->whereNull('sent_at')->sole()->lines()->sole()->quantity)->toBe('1.00');
});

test('a project without a client explains why time cannot be logged', function () {
    [$user, $issue] = timeTrackingSetup();
    $issue->project->update(['client_id' => null]);

    QuillServer::actingAs($user)->tool(LogTimeTool::class, ['issue_id' => $issue->id, 'hours' => 1])
        ->assertHasErrors(['has no client']);
});

test('members who cannot manage invoices cannot log time', function () {
    [$owner, $issue, $client] = timeTrackingSetup();
    $member = User::factory()->create();
    $client->organization->addMember($member, 'Member');
    $member->switchOrganization($client->organization);

    QuillServer::actingAs($member)->tool(LogTimeTool::class, ['issue_id' => $issue->id, 'hours' => 1])->assertHasErrors();

    expect(Invoice::query()->exists())->toBeFalse();
});

test('issues from another organization are not found', function () {
    [$user] = timeTrackingSetup();
    $stranger = Issue::factory()->create();

    QuillServer::actingAs($user)->tool(LogTimeTool::class, ['issue_id' => $stranger->id, 'hours' => 1])->assertHasErrors();
});

test('logged hours are kept as a dated entry on the invoice line', function () {
    $this->travelTo('2026-11-25 12:00:00');
    [$user, $issue] = timeTrackingSetup();

    QuillServer::actingAs($user)->tool(LogTimeTool::class, ['issue_id' => $issue->id, 'hours' => 2, 'date' => '2026-11-24'])->assertOk();

    $entry = TimeEntry::query()->sole();

    expect($entry)->issue_id->toBe($issue->id)->user_id->toBe($user->id)->description->toBe('Build the login page')
        ->and($entry->completed_at->toDateString())->toBe('2026-11-24')
        ->and($entry->invoiceLine->issue_id)->toBe($issue->id);
});
