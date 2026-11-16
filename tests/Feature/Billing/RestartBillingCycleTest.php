<?php

use App\Actions\Billing\DeleteInvoice;
use App\Actions\Billing\RestartBillingCycle;
use App\Actions\Billing\SendInvoice;
use App\Enums\BillingCycle;
use App\Enums\LineUnit;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\User;
use Carbon\CarbonImmutable;

function restartingCycle(BillingCycle $cycle = BillingCycle::Biweekly, string $start = '2026-11-14', string $end = '2026-11-27', bool $lines = true): array
{
    $organization = Organization::factory()->create();
    $owner = User::factory()->create(['timezone' => 'America/Chicago']);
    $organization->addMember($owner, 'Owner');
    $client = Client::factory()->for($organization)->create([
        'billing_cycle' => $cycle,
        'billing_cycle_starts_on' => $cycle === BillingCycle::Monthly ? null : $start,
        'billing_opened_through' => $end,
        'payment_terms_days' => 7,
    ]);
    $contact = ClientContact::factory()->for($client)->create();
    $invoice = Invoice::factory()->for($organization)->for($client)->cycle(CarbonImmutable::parse($start), CarbonImmutable::parse($end))->create([
        'contact_id' => $contact->id,
        'closes_at' => CarbonImmutable::parse($end.' 23:59:00', 'America/Chicago')->utc(),
    ]);

    if ($lines) {
        $invoice->lines()->create(['description' => 'Development', 'quantity' => '2', 'unit' => LineUnit::Hours, 'rate' => '100.00']);
    }

    return [$invoice->fresh(), $client, $owner];
}

test('sending a biweekly draft early restarts the schedule', function () {
    $this->travelTo('2026-11-25 12:00:00');
    [$invoice, $client, $owner] = restartingCycle();

    $this->actingAs($owner)->post(route('invoices.send.store', [$invoice->organization, $invoice]))->assertRedirect();

    $next = $client->fresh()->openCycleInvoice();

    expect($invoice->fresh())->service_period_end->toDateString()->toBe('2026-11-25')->closes_at->toBeNull()
        ->and($client->fresh()->billing_cycle_starts_on->toDateString())->toBe('2026-11-26')
        ->and($client->fresh()->billing_opened_through->toDateString())->toBe('2026-12-09')
        ->and($next->id)->not->toBe($invoice->id)
        ->and($next->service_period_start->toDateString())->toBe('2026-11-26')
        ->and($next->service_period_end->toDateString())->toBe('2026-12-09')
        ->and($next->closes_at->utc()->toDateTimeString())->toBe('2026-12-10 05:59:00');
});

test('sending a monthly draft early runs the next draft to the end of next month', function () {
    $this->travelTo('2026-11-25 12:00:00');
    [$invoice, $client, $owner] = restartingCycle(BillingCycle::Monthly, '2026-11-01', '2026-11-30');

    $this->actingAs($owner)->post(route('invoices.send.store', [$invoice->organization, $invoice]))->assertRedirect();

    $next = $client->fresh()->openCycleInvoice();

    expect($invoice->fresh()->service_period_end->toDateString())->toBe('2026-11-25')
        ->and($next->service_period_start->toDateString())->toBe('2026-11-26')
        ->and($next->service_period_end->toDateString())->toBe('2026-12-31')
        ->and($client->fresh()->billing_cycle_starts_on)->toBeNull()
        ->and($client->fresh()->billing_opened_through->toDateString())->toBe('2026-12-31');
});

test('deleting the open cycle draft restarts the same way', function () {
    $this->travelTo('2026-11-25 12:00:00');
    [$invoice, $client, $owner] = restartingCycle(lines: false);

    $this->actingAs($owner)->delete(route('invoices.destroy', [$invoice->organization, $invoice]))->assertRedirect();

    $next = $client->fresh()->openCycleInvoice();

    expect(Invoice::find($invoice->id))->toBeNull()
        ->and($next->service_period_start->toDateString())->toBe('2026-11-26')
        ->and($next->service_period_end->toDateString())->toBe('2026-12-09')
        ->and($client->fresh()->billing_cycle_starts_on->toDateString())->toBe('2026-11-26');
});

test('time logged after an early send lands on the new draft', function () {
    $this->travelTo('2026-11-25 12:00:00');
    [$invoice, $client, $owner] = restartingCycle();

    $this->actingAs($owner)->post(route('invoices.send.store', [$invoice->organization, $invoice]));

    expect($client->fresh()->openCycleInvoice())->not->toBeNull()->id->not->toBe($invoice->id);
});

test('the job close at period end does not restart', function () {
    $this->travelTo('2026-11-28 06:00:00');
    [$invoice, $client, $owner] = restartingCycle();

    $this->actingAs($owner)->post(route('invoices.send.store', [$invoice->organization, $invoice]))->assertRedirect();

    expect($invoice->fresh())->sent_at->not->toBeNull()->service_period_end->toDateString()->toBe('2026-11-27')
        ->and($client->fresh()->billing_cycle_starts_on->toDateString())->toBe('2026-11-14')
        ->and($client->fresh()->billing_opened_through->toDateString())->toBe('2026-11-27')
        ->and($client->fresh()->openCycleInvoice())->toBeNull();
});

test('sending a manual invoice never restarts anything', function () {
    $this->travelTo('2026-11-25 12:00:00');
    [$cycle, $client, $owner] = restartingCycle();
    $manual = Invoice::factory()->for($client->organization)->for($client)->create(['contact_id' => $cycle->contact_id]);
    $manual->lines()->create(['description' => 'Dev', 'quantity' => '1', 'unit' => LineUnit::Hours, 'rate' => '100.00']);

    $this->actingAs($owner)->post(route('invoices.send.store', [$manual->organization, $manual]))->assertRedirect();

    expect($client->fresh()->billing_cycle_starts_on->toDateString())->toBe('2026-11-14')
        ->and($client->fresh()->billing_opened_through->toDateString())->toBe('2026-11-27')
        ->and($client->fresh()->openCycleInvoice()->id)->toBe($cycle->id);
});

test('an early send is issued that day and due by the client terms', function () {
    $this->travelTo('2026-11-25 12:00:00');
    [$invoice, , $owner] = restartingCycle();

    $this->actingAs($owner)->post(route('invoices.send.store', [$invoice->organization, $invoice]))->assertRedirect();

    expect($invoice->fresh())
        ->service_period_end->toDateString()->toBe('2026-11-25')
        ->issue_date->toDateString()->toBe('2026-11-25')
        ->due_date->toDateString()->toBe('2026-12-02');
});

test('an early send falls back to workspace terms', function () {
    $this->travelTo('2026-11-25 12:00:00');
    [$invoice, $client, $owner] = restartingCycle();
    $client->update(['payment_terms_days' => null]);
    $client->organization->billingSettings()->update(['payment_terms_days' => 30]);

    $this->actingAs($owner)->post(route('invoices.send.store', [$invoice->organization, $invoice]))->assertRedirect();

    expect($invoice->fresh())
        ->issue_date->toDateString()->toBe('2026-11-25')
        ->due_date->toDateString()->toBe('2026-12-25');
});

test('deleting a draft that was sent from another tab is refused and opens nothing', function () {
    $this->travelTo('2026-11-25 12:00:00');
    [$invoice, $client] = restartingCycle();
    $stale = $invoice->fresh();

    app(SendInvoice::class)->handle($invoice);

    expect(fn () => app(DeleteInvoice::class)->handle($stale))->toThrow(DomainException::class);

    expect($invoice->fresh())->not->toBeNull()->sent_at->not->toBeNull()
        ->and($client->invoices()->whereNotNull('closes_at')->whereNull('sent_at')->count())->toBe(1)
        ->and(Invoice::count())->toBe(2);
});

test('deleting the same draft twice opens one next draft', function () {
    $this->travelTo('2026-11-25 12:00:00');
    [$invoice, $client] = restartingCycle(lines: false);
    $first = $invoice->fresh();
    $second = $invoice->fresh();

    app(DeleteInvoice::class)->handle($first);
    app(DeleteInvoice::class)->handle($second);

    $open = $client->invoices()->whereNotNull('closes_at')->whereNull('sent_at')->get();

    expect(Invoice::find($invoice->id))->toBeNull()
        ->and($open)->toHaveCount(1)
        ->and($open->first()->service_period_start->toDateString())->toBe('2026-11-26')
        ->and(Invoice::count())->toBe(1);
});

test('an existing open draft is reused instead of opening a second one', function () {
    $this->travelTo('2026-11-25 12:00:00');
    [$invoice, $client] = restartingCycle();
    $other = Invoice::factory()->for($client->organization)->for($client)->cycle(CarbonImmutable::parse('2026-11-26'), CarbonImmutable::parse('2026-12-09'))->create([
        'closes_at' => CarbonImmutable::parse('2026-12-09 23:59:00', 'America/Chicago')->utc(),
    ]);

    $next = app(RestartBillingCycle::class)->handle($invoice, CarbonImmutable::parse('2026-11-25', 'America/Chicago'));

    expect($next->id)->toBe($other->id)
        ->and(Invoice::count())->toBe(2);
});

test('nothing restarts when billing cycles are off', function () {
    $this->travelTo('2026-11-25 12:00:00');
    [$invoice, $client, $owner] = restartingCycle();
    $client->organization->updateModuleSettings(['billing_enabled' => true, 'billing_invoices_enabled' => true, 'billing_billing_cycles_enabled' => false]);

    $this->actingAs($owner)->delete(route('invoices.destroy', [$invoice->organization, $invoice]))->assertRedirect();

    expect(Invoice::count())->toBe(0)
        ->and($client->fresh()->billing_opened_through)->toBeNull();
});
