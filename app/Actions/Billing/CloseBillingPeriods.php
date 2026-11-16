<?php

namespace App\Actions\Billing;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Notifications\InvoiceCouldNotBeSent;
use App\Support\BillingEligibility;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\Notification;

class CloseBillingPeriods
{
    public function __construct(private SendInvoice $sendInvoice, private BillingEligibility $managers) {}

    public function handle(CarbonImmutable $now): void
    {
        Invoice::query()
            ->whereNotNull('closes_at')
            ->whereNull('sent_at')
            ->where('status', '!=', InvoiceStatus::Void)
            ->where('closes_at', '<=', $now)
            ->with(['organization', 'client'])
            ->get()
            ->filter(fn (Invoice $invoice): bool => $this->managers->isEligible($invoice->organization))
            ->each(fn (Invoice $invoice) => $this->close($invoice));
    }

    private function close(Invoice $invoice): void
    {
        if (! $invoice->lines()->exists() && ! $invoice->payments()->exists()) {
            $invoice->delete();

            return;
        }

        $invoice->takeMissingRecipientsFromClient();

        try {
            $this->sendInvoice->handle($invoice);
        } catch (DomainException) {
            $invoice->forceFill(['closes_at' => null])->save();
            Notification::send($this->managers->for($invoice->organization), new InvoiceCouldNotBeSent($invoice));
        }
    }
}
