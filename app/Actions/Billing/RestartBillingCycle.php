<?php

namespace App\Actions\Billing;

use App\Enums\BillingCycle;
use App\Enums\InvoiceStatus;
use App\Enums\OrganizationCapability;
use App\Models\Client;
use App\Models\Invoice;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class RestartBillingCycle
{
    public function __construct(private OpenBillingPeriod $openBillingPeriod) {}

    public function handle(Invoice $invoice, CarbonImmutable $on, bool $deleting = false): ?Invoice
    {
        if (! $invoice->organization->canUse(OrganizationCapability::BillingCycles)) {
            return null;
        }

        return DB::transaction(function () use ($invoice, $on, $deleting): ?Invoice {
            $locked = Invoice::whereKey($invoice->id)->lockForUpdate()->first();

            if ($locked === null
                || $locked->status === InvoiceStatus::Void
                || $locked->closes_at === null
                || now()->greaterThanOrEqualTo($locked->closes_at)
                || ($deleting && $locked->sent_at !== null)) {
                return null;
            }

            $client = Client::whereKey($locked->client_id)->lockForUpdate()->firstOrFail();

            if ($client->billing_cycle === null) {
                return null;
            }

            $terms = $client->payment_terms_days ?? $client->organization->billingSettings()->payment_terms_days;

            $locked->forceFill($deleting ? ['closes_at' => null] : [
                'service_period_end' => $on->toDateString(),
                'issue_date' => $on->toDateString(),
                'due_date' => $on->addDays($terms)->toDateString(),
                'closes_at' => null,
            ])->save();

            $invoice->setRawAttributes($locked->getAttributes(), true);

            $start = CarbonImmutable::parse($on->addDay()->toDateString(), $on->getTimezone());

            if ($client->billing_cycle === BillingCycle::Monthly) {
                $end = $start->day === 1 ? $start->endOfMonth() : $start->addMonthNoOverflow()->endOfMonth();
            } else {
                $client->billing_cycle_starts_on = $start;
                [, $end] = $client->billing_cycle->periodContaining($start, $start);
            }

            return $this->openBillingPeriod->openRange($client, $start, $end->startOfDay());
        });
    }
}
