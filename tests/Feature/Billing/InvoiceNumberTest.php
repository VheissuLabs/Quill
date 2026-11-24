<?php

use App\Actions\Billing\CreateInvoice;
use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Invoice;
use App\Models\Organization;

beforeEach(fn () => $this->travelTo('2026-11-15'));

function newInvoiceFor(Organization $organization, array $attributes = []): Invoice
{
    return app(CreateInvoice::class)->handle(
        $organization,
        Client::factory()->for($organization)->create(),
        $attributes,
    );
}

test('numbers follow the workspace prefix and year', function () {
    $organization = Organization::factory()->create();
    $prefixed = Organization::factory()->create();
    $prefixed->billingSettings()->update(['invoice_prefix' => 'VL']);

    expect(newInvoiceFor($organization)->number)->toBe('INV-2026-0001')
        ->and(newInvoiceFor($organization)->number)->toBe('INV-2026-0002')
        ->and(newInvoiceFor($prefixed)->number)->toBe('VL-2026-0001');
});

test('numbers continue from the highest existing one', function () {
    $organization = Organization::factory()->create();
    Invoice::factory()->for($organization)->create(['number' => 'INV-2026-0041']);
    Invoice::factory()->for($organization)->create(['number' => 'INV-2026-0009']);
    Invoice::factory()->for($organization)->create(['number' => 'INV-2025-0099']);

    expect(newInvoiceFor($organization)->number)->toBe('INV-2026-0042');
});

test('each workspace numbers on its own', function () {
    $organization = Organization::factory()->create();
    Invoice::factory()->create(['number' => 'INV-2026-0005']);

    expect(newInvoiceFor($organization)->number)->toBe('INV-2026-0001');
});

test('defaults come from the client and billing settings', function () {
    $organization = Organization::factory()->create();
    $organization->billingSettings()->update(['tax_rate' => '8.25']);
    $client = Client::factory()->for($organization)->create();
    $contact = ClientContact::factory()->for($client)->create();
    $client->update(['billing_contact_id' => $contact->id]);

    $invoice = app(CreateInvoice::class)->handle($organization, $client, []);

    expect($invoice->fresh())
        ->status->toBe(InvoiceStatus::Draft)
        ->client_id->toBe($client->id)
        ->contact_id->toBe($contact->id)
        ->tax_rate->toBe('8.25')
        ->issue_date->toDateString()->toBe('2026-11-15')
        ->due_date->toDateString()->toBe('2026-12-15')
        ->public_token->toHaveLength(40);
});

test('given attributes win over the defaults', function () {
    $organization = Organization::factory()->create();
    $organization->billingSettings()->update(['tax_rate' => '8.25']);
    $client = Client::factory()->for($organization)->create();
    $client->update(['billing_contact_id' => ClientContact::factory()->for($client)->create()->id]);

    $invoice = app(CreateInvoice::class)->handle($organization, $client, [
        'tax_rate' => null,
        'contact_id' => null,
        'due_date' => '2026-11-30',
    ]);

    expect($invoice->fresh())
        ->tax_rate->toBeNull()
        ->contact_id->toBeNull()
        ->due_date->toDateString()->toBe('2026-11-30');
});
