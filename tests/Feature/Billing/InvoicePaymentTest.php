<?php

use App\Enums\InvoiceStatus;
use App\Enums\LineUnit;
use App\Enums\PaymentMethod;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Organization;
use App\Models\User;

function paymentInvoice(string $state = 'create'): array
{
    $organization = Organization::factory()->create();
    $admin = User::factory()->create();
    $organization->addMember($admin, 'Admin');
    $client = Client::factory()->for($organization)->create();

    $factory = Invoice::factory()->for($organization)->for($client);
    $invoice = $state === 'create' ? $factory->create() : $factory->{$state}()->create();
    $invoice->lines()->create(['description' => 'Development', 'quantity' => '10', 'unit' => LineUnit::Hours, 'rate' => '100.00']);

    return [$invoice->fresh(), $admin];
}

function paymentRoute(string $name, Invoice $invoice, ?InvoicePayment $payment = null): string
{
    return route($name, array_filter(['current_organization' => $invoice->organization, 'invoice' => $invoice, 'payment' => $payment]));
}

function paymentData(array $overrides = []): array
{
    return array_merge(['amount' => '100.00', 'paid_on' => '2026-11-15', 'method' => 'check', 'reference' => 'CHK-1', 'notes' => 'Thanks'], $overrides);
}

test('recording a full payment on a sent invoice marks it paid', function () {
    [$invoice, $admin] = paymentInvoice('sent');

    $this->actingAs($admin)->post(paymentRoute('invoices.payments.store', $invoice), paymentData(['amount' => '1000.00', 'method' => 'wire']))->assertRedirect();

    expect($invoice->fresh())->status->toBe(InvoiceStatus::Paid)->and($invoice->fresh()->amountDue())->toBe('0.00')
        ->and($invoice->payments()->first())->method->toBe(PaymentMethod::Wire)->reference->toBe('CHK-1');
});

test('a deposit can be recorded on a draft', function () {
    [$invoice, $admin] = paymentInvoice();

    $this->actingAs($admin)->post(paymentRoute('invoices.payments.store', $invoice), paymentData())->assertRedirect();

    expect($invoice->fresh())->status->toBe(InvoiceStatus::Partial)->and($invoice->fresh()->isEditable())->toBeTrue();
});

test('a payment can be edited', function () {
    [$invoice, $admin] = paymentInvoice('sent');
    $payment = $invoice->payments()->create(paymentData(['method' => PaymentMethod::Check]));

    $this->actingAs($admin)->patch(paymentRoute('invoices.payments.update', $invoice, $payment), paymentData(['amount' => '1000.00']))->assertRedirect();

    expect($payment->fresh()->amount)->toBe('1000.00')->and($invoice->fresh()->status)->toBe(InvoiceStatus::Paid);
});

test('deleting a payment from a paid invoice reopens it', function () {
    [$invoice, $admin] = paymentInvoice('sent');
    $payment = $invoice->payments()->create(paymentData(['amount' => '1000.00', 'method' => PaymentMethod::Check]));
    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid);

    $this->actingAs($admin)->delete(paymentRoute('invoices.payments.destroy', $invoice, $payment))->assertRedirect();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Sent)->and(InvoicePayment::query()->count())->toBe(0);
});

test('payments cannot be added to a void invoice', function () {
    [$invoice, $admin] = paymentInvoice('void');

    $this->actingAs($admin)->post(paymentRoute('invoices.payments.store', $invoice), paymentData())->assertForbidden();

    expect($invoice->payments()->count())->toBe(0);
});

test('another invoice payment cannot be changed through this invoice', function () {
    [$invoice, $admin] = paymentInvoice('sent');
    $other = Invoice::factory()->for($invoice->organization)->for($invoice->client)->sent()->create();
    $payment = $other->payments()->create(paymentData(['method' => PaymentMethod::Check]));

    $this->actingAs($admin)->delete(paymentRoute('invoices.payments.destroy', $invoice, $payment))->assertNotFound();
});

test('members without management rights cannot record payments', function () {
    [$invoice] = paymentInvoice('sent');
    $member = User::factory()->create();
    $invoice->organization->addMember($member, 'Member');

    $this->actingAs($member)->post(paymentRoute('invoices.payments.store', $invoice), paymentData())->assertForbidden();
});

test('payment input is validated', function (array $overrides, string $field) {
    [$invoice, $admin] = paymentInvoice('sent');

    $this->actingAs($admin)->post(paymentRoute('invoices.payments.store', $invoice), paymentData($overrides))->assertSessionHasErrors($field);
})->with([
    'zero amount' => [['amount' => '0'], 'amount'],
    'negative amount' => [['amount' => '-5'], 'amount'],
    'huge amount' => [['amount' => '10000000000'], 'amount'],
    'missing amount' => [['amount' => null], 'amount'],
    'bad method' => [['method' => 'bitcoin'], 'method'],
    'bad date' => [['paid_on' => 'tomorrow-ish'], 'paid_on'],
]);

test('the invoice page offers every payment method', function () {
    [$invoice, $admin] = paymentInvoice('sent');

    $this->actingAs($admin)->get(route('invoices.show', ['current_organization' => $invoice->organization, 'invoice' => $invoice]))
        ->assertInertia(fn ($page) => $page->has('invoice.options.paymentMethods', count(PaymentMethod::cases()))
            ->where('invoice.options.paymentMethods.2', ['value' => 'wire', 'label' => 'Wire']));
});
