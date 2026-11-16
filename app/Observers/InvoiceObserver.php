<?php

namespace App\Observers;

use App\Actions\Billing\CancelSquareCheckouts;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Str;

class InvoiceObserver implements ShouldHandleEventsAfterCommit
{
    public function creating(Invoice $invoice): void
    {
        if (blank($invoice->public_token)) {
            $invoice->public_token = Str::random(40);
        }
    }

    public function updated(Invoice $invoice): void
    {
        $voided = $invoice->wasChanged('status') && $invoice->status === InvoiceStatus::Void;

        if ($voided || $invoice->wasChanged(['total', 'amount_paid'])) {
            app(CancelSquareCheckouts::class)->handle($invoice);
        }
    }
}
