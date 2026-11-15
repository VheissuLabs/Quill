<?php

use App\Enums\InvoiceStatus;
use App\Enums\LineUnit;
use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Models\InvoicePayment;

function invoiceOfOneThousand(?Invoice $invoice = null): Invoice
{
    $invoice ??= Invoice::factory()->create();

    $invoice->lines()->create([
        'description' => 'Development',
        'quantity' => '10',
        'unit' => LineUnit::Hours,
        'rate' => '100.00',
    ]);

    return $invoice->fresh();
}

function payTowards(Invoice $invoice, string $amount): InvoicePayment
{
    return $invoice->payments()->create([
        'amount' => $amount,
        'paid_on' => '2026-11-15',
        'method' => PaymentMethod::Wire,
    ]);
}

test('a fully paid draft becomes paid', function () {
    $invoice = invoiceOfOneThousand();

    payTowards($invoice, '1000.00');

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid);
});

test('a part-paid draft becomes partial', function () {
    $invoice = invoiceOfOneThousand();

    payTowards($invoice, '400.00');

    expect($invoice->fresh())
        ->status->toBe(InvoiceStatus::Partial)
        ->amountDue()->toBe('600.00');
});

test('removing the only payment from an unsent invoice returns it to draft', function () {
    $invoice = invoiceOfOneThousand();
    $payment = payTowards($invoice, '400.00');

    $payment->delete();

    expect($invoice->fresh())
        ->status->toBe(InvoiceStatus::Draft)
        ->amount_paid->toBe('0.00');
});

test('removing the only payment from a sent invoice returns it to sent', function () {
    $invoice = invoiceOfOneThousand(Invoice::factory()->sent()->create());
    $payment = payTowards($invoice, '1000.00');
    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid);

    $payment->delete();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Sent);
});

test('a zero-total sent invoice is not marked paid', function () {
    $invoice = Invoice::factory()->sent()->create();

    $invoice->recalculate();

    expect($invoice->fresh())
        ->total->toBe('0.00')
        ->status->toBe(InvoiceStatus::Sent);
});
