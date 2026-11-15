<?php

use App\Enums\AdjustmentType;
use App\Enums\InvoiceStatus;
use App\Enums\LineUnit;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\User;

function duplicableInvoice(string $state = 'sent'): array
{
    $organization = Organization::factory()->create();
    $admin = User::factory()->create();
    $organization->addMember($admin, 'Admin');
    $client = Client::factory()->for($organization)->create();
    $contact = ClientContact::factory()->for($client)->create();
    $invoice = Invoice::factory()->for($organization)->for($client)->{$state}()->create([
        'contact_id' => $contact->id,
        'cc_emails' => ['boss@client.test'],
        'tax_rate' => '10.00',
        'discount_type' => AdjustmentType::Percentage,
        'discount_value' => '5.00',
        'notes' => 'Thank you',
        'terms' => 'Net 30',
        'issue_date' => '2026-01-01',
        'due_date' => '2026-01-31',
        'service_period_start' => '2026-01-01',
        'service_period_end' => '2026-01-14',
    ]);
    $invoice->lines()->create(['description' => 'Development', 'quantity' => '10', 'unit' => LineUnit::Hours, 'rate' => '100.00', 'reference_url' => 'https://example.test/1']);
    $invoice->lines()->create(['description' => 'Design', 'quantity' => '2', 'unit' => LineUnit::Hours, 'rate' => '80.00']);
    $invoice->payments()->create(['amount' => '50.00', 'paid_on' => '2026-11-15', 'method' => 'check']);
    $invoice->recalculate();

    return [$invoice->fresh(), $admin];
}

test('duplicating copies lines into a new draft', function () {
    [$invoice, $admin] = duplicableInvoice();

    $response = $this->actingAs($admin)->post(route('invoices.duplicate.store', [$invoice->organization, $invoice]));

    $copy = Invoice::where('id', '!=', $invoice->id)->firstOrFail();

    $response->assertRedirect(route('invoices.show', [$invoice->organization, $copy]));
    expect($copy)
        ->number->not->toBe($invoice->number)
        ->status->toBe(InvoiceStatus::Draft)
        ->sent_at->toBeNull()
        ->client_id->toBe($invoice->client_id)
        ->contact_id->toBe($invoice->contact_id)
        ->cc_emails->toBe(['boss@client.test'])
        ->tax_rate->toBe('10.00')
        ->discount_value->toBe('5.00')
        ->notes->toBe('Thank you')
        ->terms->toBe('Net 30')
        ->issue_date->toDateString()->toBe(today()->toDateString())
        ->due_date->toDateString()->toBe(today()->addDays(30)->toDateString())
        ->service_period_start->toBeNull()
        ->sprint_id->toBeNull()
        ->and($copy->lines->pluck('description')->all())->toBe(['Development', 'Design'])
        ->and($copy->lines->first()->reference_url)->toBe('https://example.test/1')
        ->and($copy->payments()->count())->toBe(0)
        ->and($copy->total)->toBe($invoice->total);
});

test('void and paid invoices can be duplicated', function () {
    [$void, $admin] = duplicableInvoice('void');

    $this->actingAs($admin)->post(route('invoices.duplicate.store', [$void->organization, $void]))->assertRedirect();

    expect(Invoice::count())->toBe(2);
});

test('members without invoice permission cannot duplicate', function () {
    [$invoice] = duplicableInvoice();
    $member = User::factory()->create();
    $invoice->organization->addMember($member, 'Member');

    $this->actingAs($member)->post(route('invoices.duplicate.store', [$invoice->organization, $invoice]))->assertForbidden();
});
