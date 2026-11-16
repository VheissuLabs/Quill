<?php

namespace App\Actions\Billing;

use App\Enums\BillingCycle;
use App\Models\Client;
use App\Models\Invoice;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class RestartBillingCycle
{
    public function __construct(private OpenBillingPeriod $openBillingPeriod) {}

    /**
     * Restarts the client's schedule after a cycle invoice is sent or deleted before it closes.
     * Returns the next Draft, or null when the invoice is not an early cycle invoice.
     */
    public function handle(Invoice $invoice, CarbonImmutable $on, bool $deleting = false): ?Invoice
    {
        if ($invoice->closes_at === null || now()->greaterThanOrEqualTo($invoice->closes_at)) {
            return null;
        }

        return DB::transaction(function () use ($invoice, $on, $deleting): ?Invoice {
            $client = Client::whereKey($invoice->client_id)->lockForUpdate()->firstOrFail();

            if ($client->billing_cycle === null) {
                return null;
            }

            if (! $deleting) {
                $invoice->forceFill(['service_period_end' => $on->toDateString(), 'closes_at' => null])->save();
            }

            $start = $on->addDay();

            if ($client->billing_cycle === BillingCycle::Monthly) {
                $end = $start->day === 1 ? $start->endOfMonth() : $start->addMonthNoOverflow()->endOfMonth();
            } else {
                $client->billing_cycle_starts_on = $start;
                [, $end] = $client->billing_cycle->periodContaining($start, $start);
            }

            return $this->openBillingPeriod->openRange($client, $start->startOfDay(), $end->startOfDay());
        });
    }
}
