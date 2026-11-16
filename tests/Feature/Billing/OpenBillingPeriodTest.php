<?php

use App\Actions\Billing\OpenBillingPeriod;
use App\Enums\BillingCycle;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\Sprint;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;

function openingClient(array $attributes = []): Client
{
    $organization = Organization::factory()->create();
    $organization->addMember(User::factory()->create(['timezone' => 'America/Chicago']), 'Owner');

    return Client::factory()->for($organization)->create([
        'billing_cycle' => BillingCycle::Biweekly,
        'billing_cycle_starts_on' => '2026-11-14',
        'payment_terms_days' => 7,
        ...$attributes,
    ]);
}

function openOn(Client $client, string $date): ?Invoice
{
    return app(OpenBillingPeriod::class)->handle($client, CarbonImmutable::parse($date, 'America/Chicago')->startOfDay());
}

test('it opens the current period', function () {
    $this->travelTo('2026-11-16 12:00:00');
    $client = openingClient(['billing_cc_emails' => ['ap@example.com']]);
    $contact = ClientContact::factory()->for($client)->create();
    $client->update(['billing_contact_id' => $contact->id]);

    $invoice = openOn($client, '2026-11-16');

    expect($invoice->service_period_start->toDateString())->toBe('2026-11-14')
        ->and($invoice->service_period_end->toDateString())->toBe('2026-11-27')
        ->and($invoice->issue_date->toDateString())->toBe('2026-11-27')
        ->and($invoice->due_date->toDateString())->toBe('2026-12-04')
        ->and($invoice->contact_id)->toBe($contact->id)
        ->and($invoice->cc_emails)->toBe(['ap@example.com'])
        ->and($invoice->closes_at->utc()->toDateTimeString())->toBe('2026-11-28 05:59:00')
        ->and($client->fresh()->billing_opened_through->toDateString())->toBe('2026-11-27');
});

test('terms fall back to workspace settings', function () {
    $this->travelTo('2026-11-16 12:00:00');
    $client = openingClient(['payment_terms_days' => null]);
    $client->organization->billingSettings()->update(['payment_terms_days' => 30]);

    expect(openOn($client, '2026-11-16')->due_date->toDateString())->toBe('2026-12-27');
});

test('it returns the open draft instead of opening another', function () {
    $this->travelTo('2026-11-16 12:00:00');
    $client = openingClient();

    $first = openOn($client, '2026-11-16');
    $second = openOn($client->fresh(), '2026-11-16');

    expect($second->is($first))->toBeTrue()
        ->and(Invoice::count())->toBe(1);
});

test('nothing before the start date', function () {
    $this->travelTo('2026-11-16 12:00:00');
    $client = openingClient(['billing_cycle_starts_on' => '2026-11-20']);

    expect(openOn($client, '2026-11-16'))->toBeNull()
        ->and(Invoice::count())->toBe(0);
});

test('no cycle, no invoice', function () {
    $client = openingClient(['billing_cycle' => null, 'billing_cycle_starts_on' => null]);

    expect(openOn($client, '2026-11-16'))->toBeNull()
        ->and(Invoice::count())->toBe(0);
});

test('a covered period opens nothing', function () {
    $client = openingClient(['billing_opened_through' => '2026-11-27']);

    expect(openOn($client, '2026-11-16'))->toBeNull();
});

test('after downtime the next draft covers the missed days', function () {
    $this->travelTo('2026-12-20 12:00:00');
    $client = openingClient(['billing_opened_through' => '2026-11-27']);

    $invoice = openOn($client, '2026-12-20');

    expect($invoice->service_period_start->toDateString())->toBe('2026-11-28')
        ->and($invoice->service_period_end->toDateString())->toBe('2026-12-25');
});

test('a cycle change continues without gap or overlap', function () {
    $this->travelTo('2026-11-28 12:00:00');
    $client = openingClient([
        'billing_cycle' => BillingCycle::Monthly,
        'billing_cycle_starts_on' => null,
        'billing_opened_through' => '2026-11-27',
    ]);

    $invoice = openOn($client, '2026-11-28');

    expect($invoice->service_period_start->toDateString())->toBe('2026-11-28')
        ->and($invoice->service_period_end->toDateString())->toBe('2026-11-30');
});

test("it links the sprint with exactly the period's dates", function () {
    $this->travelTo('2026-11-16 12:00:00');
    $client = openingClient();
    $organization = $client->organization;
    $team = Team::factory()->for($organization)->create();
    $dates = ['starts_at' => '2026-11-14', 'ends_at' => '2026-11-27'];
    Sprint::factory()->for($team, 'owner')->create($dates);
    $workspace = Sprint::factory()->for($organization, 'owner')->create($dates);

    expect(openOn($client, '2026-11-16')->sprint_id)->toBe($workspace->id);
});

test('a sprint with different dates is not linked', function () {
    $this->travelTo('2026-11-16 12:00:00');
    $client = openingClient();
    Sprint::factory()->for($client->organization, 'owner')->create(['starts_at' => '2026-11-14', 'ends_at' => '2026-11-28']);

    expect(openOn($client, '2026-11-16')->sprint_id)->toBeNull();
});

test('it opens without a billing contact', function () {
    $this->travelTo('2026-11-16 12:00:00');
    $client = openingClient();

    expect(openOn($client, '2026-11-16')->contact_id)->toBeNull();
});

dataset('eastern timezones', ['Europe/Paris', 'Asia/Tokyo']);

test('a draft opens on the first day of a period east of UTC', function (string $timezone) {
    $this->travelTo(CarbonImmutable::parse('2026-11-28 09:00:00', $timezone));
    $client = openingClient(['billing_opened_through' => '2026-11-27']);
    $client->organization->members()->first()->update(['timezone' => $timezone]);

    $invoice = app(OpenBillingPeriod::class)->handle($client, CarbonImmutable::parse('2026-11-28', $timezone));

    expect($invoice)->not->toBeNull()
        ->and($invoice->service_period_start->toDateString())->toBe('2026-11-28')
        ->and($invoice->service_period_end->toDateString())->toBe('2026-12-11')
        ->and($invoice->closes_at->utc()->toDateTimeString())->toBe(CarbonImmutable::parse('2026-12-11 23:59:00', $timezone)->utc()->toDateTimeString());
})->with('eastern timezones');

test('a draft opens on the start date itself east of UTC', function (string $timezone) {
    $this->travelTo(CarbonImmutable::parse('2026-11-14 09:00:00', $timezone));
    $client = openingClient();
    $client->organization->members()->first()->update(['timezone' => $timezone]);

    $invoice = app(OpenBillingPeriod::class)->handle($client, CarbonImmutable::parse('2026-11-14', $timezone));

    expect($invoice)->not->toBeNull()
        ->and($invoice->service_period_start->toDateString())->toBe('2026-11-14')
        ->and($invoice->service_period_end->toDateString())->toBe('2026-11-27');
})->with('eastern timezones');

test('the billing run opens the next period just after local midnight east of UTC', function (string $timezone) {
    $client = openingClient(['billing_opened_through' => '2026-11-27']);
    $client->organization->members()->first()->update(['timezone' => $timezone]);
    $this->travelTo(CarbonImmutable::parse('2026-11-28 00:01:00', $timezone)->utc());

    $this->artisan('billing:run')->assertSuccessful();

    expect($client->fresh()->openCycleInvoice()?->service_period_start->toDateString())->toBe('2026-11-28');
})->with('eastern timezones');
