<?php

namespace App\Observers;

use App\Models\Invoice;
use Illuminate\Support\Str;

class InvoiceObserver
{
    public function creating(Invoice $invoice): void
    {
        if (blank($invoice->public_token)) {
            $invoice->public_token = Str::random(40);
        }
    }
}
