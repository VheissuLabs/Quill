<?php

namespace App\Observers;

use App\Models\InvoicePayment;

class InvoicePaymentObserver
{
    public function saved(InvoicePayment $payment): void
    {
        $payment->invoice()->first()?->recalculate();
    }

    public function deleted(InvoicePayment $payment): void
    {
        $payment->invoice()->first()?->recalculate();
    }
}
