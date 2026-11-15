<?php

namespace App\Actions\Billing;

use App\Models\Invoice;
use App\Notifications\InvoiceSent;
use Illuminate\Support\Facades\Notification;

class SendInvoice
{
    public function handle(Invoice $invoice): void
    {
        $invoice->forceFill(['sent_at' => now()])->save();
        $invoice->recalculate();

        $contact = $invoice->contact;

        if ($contact === null) {
            return;
        }

        Notification::route('mail', [$contact->email => $contact->name])->notify(new InvoiceSent($invoice));
    }
}
