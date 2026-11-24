<?php

namespace App\Actions\Billing;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use Illuminate\Support\Facades\DB;

class DuplicateInvoice
{
    public function __construct(private CreateInvoice $createInvoice) {}

    public function handle(Invoice $invoice): Invoice
    {
        return DB::transaction(function () use ($invoice): Invoice {
            $copy = $this->createInvoice->handle($invoice->organization, $invoice->client, [
                'project_id' => $invoice->project_id,
                'contact_id' => $invoice->contact_id,
                'cc_emails' => $invoice->cc_emails,
                'tax_rate' => $invoice->tax_rate,
                'discount_type' => $invoice->discount_type,
                'discount_value' => $invoice->discount_value,
                'deposit_type' => $invoice->deposit_type,
                'deposit_value' => $invoice->deposit_value,
            ]);

            $invoice->lines->each(fn (InvoiceLine $line) => $copy->lines()->create($line->only([
                'description', 'quantity', 'unit', 'rate', 'sort_order', 'issue_id', 'reference_url', 'work_type_id',
            ])));

            $copy->recalculate();

            return $copy;
        });
    }
}
