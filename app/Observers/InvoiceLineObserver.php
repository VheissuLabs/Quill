<?php

namespace App\Observers;

use App\Models\InvoiceLine;

class InvoiceLineObserver
{
    public function saving(InvoiceLine $line): void
    {
        $line->amount = round((float) $line->quantity * (float) $line->rate, 2);
    }

    public function saved(InvoiceLine $line): void
    {
        $line->invoice()->first()?->recalculate();
    }

    public function deleted(InvoiceLine $line): void
    {
        $line->invoice()->first()?->recalculate();
    }
}
