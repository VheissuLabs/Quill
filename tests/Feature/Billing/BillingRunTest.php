<?php

use App\Enums\BillingCycle;
use App\Enums\InvoiceStatus;
use App\Enums\LineUnit;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\InvoiceClosingSoon;
use App\Notifications\InvoiceCouldNotBeSent;
use App\Notifications\InvoiceSent;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Notification;

function cycleWorkspace(bool $contact = true, array $invoice = []): array
{
    $organization = Organization::factory()->create();
    $owner = User::factory()->create(['timezone' => 'America/Chicago']);
    $organization->addMember($owner, 'Owner');

    $client = Client::factory()->for($organization)->create([
        'billing_cycle' => BillingCycle::Biweekly,
        'billing_cycle_starts_on' => '2026-11-14',
        'billing_opened_through' => '2026-11-27',
        'payment_terms_days' => 7,
    ]);
    $recipient = $contact ? ClientContact::factory()->for($client)->create() : null;

    $draft = Invoice::factory()->for($organization)->for($client)
        ->cycle(CarbonImmutable::parse('2026-11-14'), CarbonImmutable::parse('2026-11-27'))
        ->create([
            'contact_id' => $recipient?->id,
            'closes_at' => '2026-11-28 05:59:00',
            ...$invoice,
        ]);

    return [$draft, $owner, $client];
}

function addLine(Invoice $invoice): void
{
    $invoice->lines()->create(['description' => 'Development', 'quantity' => '10', 'unit' => LineUnit::Hours, 'rate' => '100.00']);
}

function runBilling(string $at): void
{
    test()->travelTo(CarbonImmutable::parse($at, 'UTC'));
    test()->artisan('billing:run')->assertSuccessful();
}

test('it reminds at 3 PM on the last day when there are lines', function () {
    Notification::fake();
    [$draft, $owner] = cycleWorkspace();
    addLine($draft);

    runBilling('2026-11-27 20:59:00');
    Notification::assertNothingSent();

    runBilling('2026-11-27 21:00:00');
    Notification::assertSentTo($owner, InvoiceClosingSoon::class);
    expect($draft->fresh()->reminded_at)->not->toBeNull();

    runBilling('2026-11-27 21:05:00');
    Notification::assertSentToTimes($owner, InvoiceClosingSoon::class, 1);
});

test('no lines, no reminder', function () {
    Notification::fake();
    cycleWorkspace();

    runBilling('2026-11-27 22:00:00');

    Notification::assertNothingSent();
});

test('it sends at 11:59 PM', function () {
    Notification::fake();
    [$draft] = cycleWorkspace();
    addLine($draft);

    runBilling('2026-11-28 05:58:00');
    expect($draft->fresh()->sent_at)->toBeNull();

    runBilling('2026-11-28 05:59:00');
    expect($draft->fresh()->sent_at)->not->toBeNull();
    Notification::assertSentOnDemandTimes(InvoiceSent::class, 1);

    runBilling('2026-11-28 06:00:00');
    Notification::assertSentOnDemandTimes(InvoiceSent::class, 1);
    expect($draft->client->invoices()->whereNull('sent_at')->whereNotNull('closes_at')->first())
        ->service_period_start->toDateString()->toBe('2026-11-28');
});

test('an empty period is deleted without email', function () {
    Notification::fake();
    [$draft, $owner] = cycleWorkspace();

    runBilling('2026-11-28 05:59:00');

    expect(Invoice::find($draft->id))->toBeNull();
    Notification::assertNothingSent();
});

test('a draft with a payment but no lines is not deleted', function () {
    Notification::fake();
    [$draft] = cycleWorkspace();
    $draft->payments()->create(['amount' => '100.00', 'paid_on' => '2026-11-20', 'method' => 'check']);

    runBilling('2026-11-28 05:59:00');

    expect(Invoice::find($draft->id))->not->toBeNull();
    Notification::assertSentOnDemandTimes(InvoiceSent::class, 0);
});

test('no contact: it becomes a manual draft and managers are told', function () {
    Notification::fake();
    [$draft, $owner, $client] = cycleWorkspace(contact: false);
    addLine($draft);

    runBilling('2026-11-28 05:59:00');
    runBilling('2026-11-28 06:00:00');

    expect($draft->fresh())->closes_at->toBeNull()->sent_at->toBeNull()->status->toBe(InvoiceStatus::Draft);
    Notification::assertSentToTimes($owner, InvoiceCouldNotBeSent::class, 1);
    expect($client->invoices()->whereNotNull('closes_at')->whereNull('sent_at')->first())
        ->service_period_start->toDateString()->toBe('2026-11-28');
});

test('a draft already paid in full by a deposit is still sent', function () {
    Notification::fake();
    [$draft] = cycleWorkspace();
    addLine($draft);
    $draft->payments()->create(['amount' => '1000.00', 'paid_on' => '2026-11-20', 'method' => 'check']);
    $draft->recalculate();

    runBilling('2026-11-28 05:59:00');

    expect($draft->fresh())->sent_at->not->toBeNull()->status->toBe(InvoiceStatus::Paid);
    Notification::assertSentOnDemandTimes(InvoiceSent::class, 1);
});

test('sent invoices past due become overdue; partial stays partial', function () {
    Notification::fake();
    [$draft, , $client] = cycleWorkspace();
    $make = fn (InvoiceStatus $status, string $due) => Invoice::factory()->for($client->organization)->for($client)
        ->create(['status' => $status, 'sent_at' => now(), 'due_date' => $due]);
    $sent = $make(InvoiceStatus::Sent, '2026-11-15');
    $partial = $make(InvoiceStatus::Partial, '2026-11-15');
    $dueToday = $make(InvoiceStatus::Sent, '2026-11-16');

    runBilling('2026-11-16 07:00:00');

    expect($sent->fresh()->status)->toBe(InvoiceStatus::Overdue)
        ->and($partial->fresh()->status)->toBe(InvoiceStatus::Partial)
        ->and($dueToday->fresh()->status)->toBe(InvoiceStatus::Sent);
});

test('workspaces without the cycles capability are skipped', function () {
    Notification::fake();
    [$draft, , $client] = cycleWorkspace();
    addLine($draft);
    $client->organization->updateModuleSettings(['billing_enabled' => true, 'billing_invoices_enabled' => true, 'billing_billing_cycles_enabled' => false]);

    runBilling('2026-11-28 05:59:00');

    expect($draft->fresh()->sent_at)->toBeNull();
    Notification::assertNothingSent();
});

test('the command is scheduled every minute', function () {
    $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains($event->command, 'billing:run'));

    expect($event)->not->toBeNull()->and($event->expression)->toBe('* * * * *');
});
