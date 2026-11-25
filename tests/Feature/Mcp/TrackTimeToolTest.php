<?php

use App\Enums\BillingCycle;
use App\Mcp\Servers\QuillServer;
use App\Mcp\Tools\Billing\TrackTimeTool;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkType;
use Carbon\CarbonImmutable;

function clientWorkSetup(array $client = []): array
{
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $organization->addMember($user, 'Owner');
    $user->switchOrganization($organization);

    return [$user, Client::factory()->for($organization)->create(['billing_rate' => 120, ...$client])];
}

test('time for a client rolls onto their draft so its total shows what is expected', function () {
    $this->travelTo('2026-11-25 12:00:00');
    [$user, $client] = clientWorkSetup(['name' => 'RTF']);
    $meetings = WorkType::factory()->for($client->organization)->create(['name' => 'Meetings']);

    QuillServer::actingAs($user)->tool(TrackTimeTool::class, ['client' => $client->slug, 'description' => 'Onboarding', 'hours' => 4, 'date' => '2026-11-24', 'reference_url' => 'https://rtf.atlassian.net/browse/RTF-12'])->assertOk();
    QuillServer::actingAs($user)->tool(TrackTimeTool::class, ['client' => $client->slug, 'description' => 'Daily Stand Up', 'hours' => 0.25, 'date' => '2026-11-24', 'work_type_id' => $meetings->id])->assertOk();
    QuillServer::actingAs($user)->tool(TrackTimeTool::class, ['client' => $client->slug, 'description' => 'Daily Stand Up', 'hours' => 0.25, 'work_type_id' => $meetings->id])
        ->assertOk()
        ->assertSee('540.00');

    $invoice = $client->invoices()->sole();

    expect($invoice->lines->map->only(['description', 'quantity', 'reference_url', 'work_type_id'])->all())->toBe([
        ['description' => 'Onboarding', 'quantity' => '4.00', 'reference_url' => 'https://rtf.atlassian.net/browse/RTF-12', 'work_type_id' => null],
        ['description' => 'Daily Stand Up', 'quantity' => '0.50', 'reference_url' => null, 'work_type_id' => $meetings->id],
    ])
        ->and($invoice->total)->toBe('540.00')
        ->and($invoice->service_period_start->toDateString())->toBe('2026-11-24')
        ->and($invoice->service_period_end->toDateString())->toBe('2026-11-25')
        ->and(TimeEntry::query()->orderBy('id')->pluck('worked_on')->map->toDateString()->all())->toBe(['2026-11-24', '2026-11-24', '2026-11-25'])
        ->and(TimeEntry::query()->whereNull('invoice_line_id')->exists())->toBeFalse();
});

test('entries for the same ticket add to one line even when described differently', function () {
    [$user, $client] = clientWorkSetup();
    $ticket = 'https://github.com/acme/app/issues/42';

    QuillServer::actingAs($user)->tool(TrackTimeTool::class, ['client' => $client->slug, 'description' => 'Fix checkout', 'hours' => 1, 'reference_url' => $ticket]);
    QuillServer::actingAs($user)->tool(TrackTimeTool::class, ['client' => $client->slug, 'description' => 'Checkout review fixes', 'hours' => 0.5, 'reference_url' => $ticket])->assertOk();

    expect($client->invoices()->sole()->lines()->sole())->quantity->toBe('1.50')->description->toBe('Fix checkout');
});

test('time goes on the invoice for the billing period containing its date', function () {
    $this->travelTo('2026-11-16 12:00:00');
    [$user, $client] = clientWorkSetup(['billing_cycle' => BillingCycle::Weekly, 'billing_cycle_starts_on' => '2026-11-02']);
    $lastWeek = Invoice::factory()->for($client->organization)->for($client)
        ->cycle(CarbonImmutable::parse('2026-11-09'), CarbonImmutable::parse('2026-11-15'))
        ->create(['closes_at' => null]);

    QuillServer::actingAs($user)->tool(TrackTimeTool::class, ['client' => $client->slug, 'description' => 'Late notes', 'hours' => 1, 'date' => '2026-11-13'])->assertOk();
    QuillServer::actingAs($user)->tool(TrackTimeTool::class, ['client' => $client->slug, 'description' => 'New work', 'hours' => 2, 'date' => '2026-11-16'])->assertOk();

    expect($lastWeek->lines()->sole()->description)->toBe('Late notes')
        ->and($client->openCycleInvoice()->lines()->sole()->description)->toBe('New work');
});

test('time in a period whose invoice was sent is recorded but not billed', function () {
    $this->travelTo('2026-11-16 12:00:00');
    [$user, $client] = clientWorkSetup(['billing_cycle' => BillingCycle::Weekly, 'billing_cycle_starts_on' => '2026-11-02']);
    $sent = Invoice::factory()->for($client->organization)->for($client)
        ->cycle(CarbonImmutable::parse('2026-11-09'), CarbonImmutable::parse('2026-11-15'))
        ->sent()
        ->create(['number' => 'INV-2026-0007']);

    QuillServer::actingAs($user)->tool(TrackTimeTool::class, ['client' => $client->slug, 'description' => 'Forgot this', 'hours' => 1, 'date' => '2026-11-12'])
        ->assertOk()
        ->assertSee('INV-2026-0007, which has already been sent');

    expect(TimeEntry::query()->sole())->invoice_line_id->toBeNull()->hours->toBe('1.00')
        ->and($sent->lines()->exists())->toBeFalse();
});

test('time for a client billed through another goes on the other client\'s draft', function () {
    [$user, $client] = clientWorkSetup(['billing_rate' => null]);
    $parent = Client::factory()->for($client->organization)->create(['billing_rate' => 90]);
    $client->update(['billed_through_client_id' => $parent->id]);

    QuillServer::actingAs($user)->tool(TrackTimeTool::class, ['client' => $client->slug, 'description' => 'Support', 'hours' => 1])->assertOk();

    expect($parent->invoices()->sole()->lines()->sole()->rate)->toBe('90.00')
        ->and(TimeEntry::query()->sole()->client_id)->toBe($client->id);
});

test('time cannot be dated in the future', function () {
    $this->travelTo('2026-11-25 12:00:00');
    [$user, $client] = clientWorkSetup();

    QuillServer::actingAs($user)->tool(TrackTimeTool::class, ['client' => $client->slug, 'description' => 'Planning', 'hours' => 1, 'date' => '2026-11-26'])
        ->assertHasErrors(['date']);

    expect(TimeEntry::query()->exists())->toBeFalse();
});

test('a work type from another organization is rejected', function () {
    [$user, $client] = clientWorkSetup();

    QuillServer::actingAs($user)->tool(TrackTimeTool::class, ['client' => $client->slug, 'description' => 'Planning', 'hours' => 1, 'work_type_id' => WorkType::factory()->create()->id])
        ->assertHasErrors(['work type']);

    expect(TimeEntry::query()->exists())->toBeFalse();
});

test('clients from another organization are not found', function () {
    [$user] = clientWorkSetup();
    $stranger = Client::factory()->create();

    QuillServer::actingAs($user)->tool(TrackTimeTool::class, ['client' => $stranger->slug, 'description' => 'Planning', 'hours' => 1])
        ->assertHasErrors(['Nothing matched']);

    expect(TimeEntry::query()->exists())->toBeFalse();
});

test('members who cannot manage invoices cannot track time', function () {
    [, $client] = clientWorkSetup();
    $member = User::factory()->create();
    $client->organization->addMember($member, 'Member');
    $member->switchOrganization($client->organization);

    QuillServer::actingAs($member)->tool(TrackTimeTool::class, ['client' => $client->slug, 'description' => 'Planning', 'hours' => 1])->assertHasErrors();

    expect(TimeEntry::query()->exists())->toBeFalse();
});
