<?php

namespace App\Actions\Billing;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Notifications\InvoiceSent;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class SendInvoice
{
    public function handle(Invoice $invoice): void
    {
        DB::transaction(function () use ($invoice): void {
            $locked = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($locked->sent_at !== null || $locked->status === InvoiceStatus::Void) {
                return;
            }

            $contact = $locked->contact ?? throw new DomainException(__('An invoice needs a contact before it can be sent.'));

            if (! $locked->lines()->exists()) {
                throw new DomainException(__('An invoice needs at least one line before it can be sent.'));
            }

            $locked->forceFill(['sent_at' => now()])->save();
            $locked->recalculate();

            Notification::route('mail', [$contact->email => $contact->name])->notify(new InvoiceSent($locked));

            $invoice->setRawAttributes($locked->getAttributes(), true);
        });
    }
}
