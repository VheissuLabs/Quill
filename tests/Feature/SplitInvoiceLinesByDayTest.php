<?php

use App\Actions\Billing\SplitInvoiceLinesByDay;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\TimeEntry;

function lineWithEntries(Invoice $invoice, string $quantity, array $days): InvoiceLine
{
    $line = InvoiceLine::factory()->for($invoice)->create(['description' => 'Onboarding', 'quantity' => $quantity, 'rate' => '55.00']);

    foreach ($days as $day => $hours) {
        TimeEntry::factory()->for($invoice->client)->create(['invoice_line_id' => $line->id, 'completed_at' => $day, 'hours' => $hours, 'description' => 'Onboarding']);
    }

    return $line;
}

test('a draft line holding several days of time becomes one line per day', function () {
    $invoice = Invoice::factory()->create();
    lineWithEntries($invoice, '7.34', ['2026-11-24' => '3.67', '2026-11-25' => '3.67']);

    app(SplitInvoiceLinesByDay::class)->handle($invoice);

    expect($invoice->lines()->get()->map(fn (InvoiceLine $line) => [$line->completed_at->toDateString(), $line->quantity, $line->timeEntries()->count()])->all())->toBe([
        ['2026-11-24', '3.67', 1],
        ['2026-11-25', '3.67', 1],
    ])
        ->and($invoice->fresh()->total)->toBe('403.70');
});

test('a line whose hours were edited by hand is left alone', function () {
    $invoice = Invoice::factory()->create();
    $line = lineWithEntries($invoice, '8.00', ['2026-11-24' => '3.67', '2026-11-25' => '3.67']);

    app(SplitInvoiceLinesByDay::class)->handle($invoice);

    expect($invoice->lines()->sole())->id->toBe($line->id)->completed_at->toBeNull()->quantity->toBe('8.00');
});

test('sent invoices are never split', function () {
    $invoice = Invoice::factory()->sent()->create();
    lineWithEntries($invoice, '7.34', ['2026-11-24' => '3.67', '2026-11-25' => '3.67']);

    app(SplitInvoiceLinesByDay::class)->handle($invoice);

    expect($invoice->lines()->sole()->completed_at)->toBeNull();
});
