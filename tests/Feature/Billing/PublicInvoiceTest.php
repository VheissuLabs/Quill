<?php

use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('anyone with the link sees a sent invoice', function () {
    $invoice = Invoice::factory()->sent()->create();
    $invoice->lines()->create(['description' => 'Development', 'quantity' => '10', 'unit' => 'hours', 'rate' => '100.00']);

    $this->get($invoice->publicUrl())
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('invoices/Public')
            ->where('invoice.number', $invoice->number)
            ->where('invoice.amountDue', '1000.00')
            ->where('invoice.isVoid', false)
            ->has('invoice.lines', 1));
});

test('a draft link is not found', function () {
    $invoice = Invoice::factory()->create();

    $this->get($invoice->publicUrl())->assertNotFound();
});

test('a void link shows that the invoice is void', function () {
    $invoice = Invoice::factory()->void()->create();

    $this->get($invoice->publicUrl())
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('invoice.isVoid', true));
});

test('the public page leaks nothing internal', function () {
    $invoice = Invoice::factory()->sent()->create();
    $invoice->lines()->create(['description' => 'Development', 'quantity' => '1', 'unit' => 'hours', 'rate' => '100.00']);
    $invoice->payments()->create(['amount' => '10.00', 'paid_on' => '2026-11-15', 'method' => PaymentMethod::Wire]);

    $this->get($invoice->publicUrl())
        ->assertInertia(fn (Assert $page) => $page
            ->missing('invoice.id')
            ->missing('invoice.organization_id')
            ->missing('invoice.client_id')
            ->missing('invoice.clientId')
            ->missing('invoice.public_token')
            ->missing('invoice.lines.0.id')
            ->missing('invoice.lines.0.issueId')
            ->missing('invoice.payments.0.id'));
});

test('the internal invoice carries the public link only once sent', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create();
    $organization->addMember($admin, 'Admin');
    $sent = Invoice::factory()->sent()->for($organization)->create();

    $this->actingAs($admin)
        ->get(route('invoices.show', ['current_organization' => $organization, 'invoice' => $sent]))
        ->assertInertia(fn (Assert $page) => $page->where('invoice.publicUrl', $sent->publicUrl()));
});
