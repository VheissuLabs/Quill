<?php

namespace App\Actions\Billing;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

class DeleteInvoice
{
    public function __construct(private RestartBillingCycle $restartBillingCycle) {}

    public function handle(Invoice $invoice): void
    {
        DB::transaction(function () use ($invoice): void {
            $locked = Invoice::whereKey($invoice->id)->lockForUpdate()->first();

            if ($locked === null) {
                return;
            }

            if ($locked->status !== InvoiceStatus::Draft || $locked->sent_at !== null || $locked->payments()->exists()) {
                throw new DomainException(__('Only an unsent draft without payments can be deleted.'));
            }

            $this->restartBillingCycle->handle($locked, CarbonImmutable::now($locked->organization->billingTimezone())->startOfDay(), deleting: true);

            $locked->delete();
        });
    }
}
