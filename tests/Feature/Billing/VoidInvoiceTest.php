<?php

use App\Enums\InvoiceStatus;
use App\Enums\LineUnit;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\User;

function voidableInvoice(string $state = 'sent'): array
{
    $organization = Organization::factory()->create();
    $admin = User::factory()->create();
    $organization->addMember($admin, 'Admin');
    $client = Client::factory()->for($organization)->create();
    $invoice = Invoice::factory()->for($organization)->for($client)->{$state}()->create();
    $invoice->lines()->create(['description' => 'Development', 'quantity' => '10', 'unit' => LineUnit::Hours, 'rate' => '100.00']);

    return [$invoice->fresh(), $admin];
}

test('a sent invoice can be voided and becomes read-only', function () {
    [$invoice, $admin] = voidableInvoice();

    $this->actingAs($admin)->post(route('invoices.void.store', [$invoice->organization, $invoice]))->assertRedirect();

    expect($invoice->fresh())->status->toBe(InvoiceStatus::Void)->voided_at->not->toBeNull();

    $this->actingAs($admin)->patch(route('invoices.update', [$invoice->organization, $invoice]), ['cc_emails' => ['changed@example.com']])->assertForbidden();
});

test('a paid invoice cannot be voided', function () {
    [$invoice, $admin] = voidableInvoice();
    $invoice->payments()->create(['amount' => '1000.00', 'paid_on' => '2026-11-15', 'method' => 'check']);
    $invoice->fresh()->recalculate();

    $this->actingAs($admin)->post(route('invoices.void.store', [$invoice->organization, $invoice]))->assertForbidden();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid);
});

test('a draft or already void invoice cannot be voided', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create();
    $organization->addMember($admin, 'Admin');
    $draft = Invoice::factory()->for($organization)->create();
    $void = Invoice::factory()->for($organization)->void()->create();

    $this->actingAs($admin)->post(route('invoices.void.store', [$organization, $draft]))->assertForbidden();
    $this->actingAs($admin)->post(route('invoices.void.store', [$organization, $void]))->assertForbidden();
});
