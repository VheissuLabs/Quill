<?php

namespace App\Actions\Billing;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Notifications\InvoiceClosingSoon;
use App\Support\BillingEligibility;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;

class RemindClosingInvoices
{
    public function __construct(private BillingEligibility $managers) {}

    public function handle(CarbonImmutable $now): void
    {
        Invoice::query()
            ->whereNotNull('closes_at')
            ->whereNull('sent_at')
            ->whereNull('reminded_at')
            ->where('status', '!=', InvoiceStatus::Void)
            ->where('closes_at', '>', $now)
            ->where('closes_at', '<=', $now->addHours(10))
            ->whereHas('lines')
            ->with(['organization', 'client'])
            ->get()
            ->each(function (Invoice $invoice) use ($now): void {
                $organization = $invoice->organization;

                if (! $this->managers->isEligible($organization)) {
                    return;
                }

                $local = $now->setTimezone($organization->billingTimezone());
                $closes = $invoice->closes_at->toImmutable()->setTimezone($local->getTimezone());

                if ($local->toDateString() !== $closes->toDateString() || $local->hour < 15) {
                    return;
                }

                $invoice->takeMissingRecipientsFromClient();
                $invoice->forceFill(['reminded_at' => $now])->save();
                Notification::send($this->managers->for($organization), new InvoiceClosingSoon($invoice));
            });
    }
}
