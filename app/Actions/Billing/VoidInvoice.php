<?php

namespace App\Actions\Billing;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;

class VoidInvoice
{
    public function handle(Invoice $invoice): void
    {
        $invoice->forceFill(['status' => InvoiceStatus::Void, 'voided_at' => now()])->save();
    }
}
