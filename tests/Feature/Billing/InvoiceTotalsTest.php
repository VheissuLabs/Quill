<?php

use App\Enums\AdjustmentType;
use App\Enums\InvoiceStatus;
use App\Enums\LineUnit;
use App\Enums\PaymentMethod;
use App\Models\Invoice;

function addHours(Invoice $invoice, string $hours, string $rate): void
{
    $invoice->lines()->create([
        'description' => 'Development',
        'quantity' => $hours,
        'unit' => LineUnit::Hours,
        'rate' => $rate,
    ]);
}

function recordPayment(Invoice $invoice, string $amount): void
{
    $invoice->payments()->create([
        'amount' => $amount,
        'paid_on' => '2026-11-15',
        'method' => PaymentMethod::Check,
    ]);
}

test('totals tax the subtotal before the discount', function () {
    $invoice = Invoice::factory()->create([
        'tax_rate' => '8.25',
        'discount_type' => AdjustmentType::Percentage,
        'discount_value' => '10',
        'deposit_type' => AdjustmentType::Percentage,
        'deposit_value' => '50',
    ]);

    addHours($invoice, '10', '100.00');
    addHours($invoice, '2.5', '80.00');

    expect($invoice->fresh())
        ->subtotal->toBe('1200.00')->tax_amount->toBe('99.00')
        ->discount_amount->toBe('120.00')->total->toBe('1179.00')
        ->deposit_amount->toBe('589.50');
});

test('a fixed discount and deposit are taken as given', function () {
    $invoice = Invoice::factory()->create([
        'tax_rate' => '8.25',
        'discount_type' => AdjustmentType::Fixed,
        'discount_value' => '50',
        'deposit_type' => AdjustmentType::Fixed,
        'deposit_value' => '300',
    ]);

    addHours($invoice, '10', '100.00');
    addHours($invoice, '2.5', '80.00');

    expect($invoice->fresh())
        ->discount_amount->toBe('50.00')->total->toBe('1249.00')
        ->deposit_amount->toBe('300.00');
});

test('amount due never goes below zero', function () {
    $invoice = Invoice::factory()->create([
        'tax_rate' => '8.25',
        'discount_type' => AdjustmentType::Percentage,
        'discount_value' => '10',
    ]);
    addHours($invoice, '10', '100.00');
    addHours($invoice, '2.5', '80.00');

    recordPayment($invoice, '1300.00');

    expect($invoice->fresh())
        ->total->toBe('1179.00')
        ->amount_paid->toBe('1300.00')
        ->amountDue()->toBe('0.00');
});

test('a void invoice is never recalculated', function () {
    $invoice = Invoice::factory()->create();
    addHours($invoice, '1', '100.00');
    $invoice->fresh()->update(['status' => InvoiceStatus::Void, 'voided_at' => now()]);

    recordPayment($invoice->fresh(), '100.00');
    addHours($invoice->fresh(), '1', '50.00');

    expect($invoice->fresh())
        ->status->toBe(InvoiceStatus::Void)
        ->total->toBe('100.00')
        ->amount_paid->toBe('0.00');
});
