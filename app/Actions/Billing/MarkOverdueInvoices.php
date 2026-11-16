<?php

namespace App\Actions\Billing;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Support\BillingEligibility;
use Carbon\CarbonImmutable;

class MarkOverdueInvoices
{
    public function __construct(private BillingEligibility $managers) {}

    public function handle(CarbonImmutable $now): void
    {
        Invoice::query()
            ->where('status', InvoiceStatus::Sent)
            ->where('due_date', '<=', $now->addDay()->toDateString())
            ->with('organization')
            ->get()
            ->filter(fn (Invoice $invoice): bool => $this->managers->isEligible($invoice->organization))
            ->each(function (Invoice $invoice) use ($now): void {
                $today = $now->setTimezone($invoice->organization->billingTimezone())->toDateString();

                if ($invoice->due_date->toDateString() < $today) {
                    $invoice->update(['status' => InvoiceStatus::Overdue]);
                }
            });
    }
}
