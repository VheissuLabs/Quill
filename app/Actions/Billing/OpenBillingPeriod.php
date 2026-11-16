<?php

namespace App\Actions\Billing;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Sprint;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class OpenBillingPeriod
{
    public function __construct(private CreateInvoice $createInvoice) {}

    public function handle(Client $client, CarbonImmutable $today): ?Invoice
    {
        if ($client->billing_cycle === null) {
            return null;
        }

        return DB::transaction(function () use ($client, $today): ?Invoice {
            $locked = Client::whereKey($client->id)->lockForUpdate()->firstOrFail();

            if ($open = $locked->openCycleInvoice()) {
                return $open;
            }

            $period = $locked->billing_cycle?->periodContaining(
                $today,
                $locked->billing_cycle_starts_on ? CarbonImmutable::parse($locked->billing_cycle_starts_on->toDateString()) : null,
            );

            if ($period === null) {
                return null;
            }

            [$periodStart, $end] = $period;
            $openedThrough = $locked->billing_opened_through
                ? CarbonImmutable::parse($locked->billing_opened_through->toDateString())
                : null;

            if ($openedThrough !== null && $end->lessThanOrEqualTo($openedThrough)) {
                return null;
            }

            return $this->openRange($locked, $openedThrough?->addDay() ?? $periodStart, $end, $client);
        });
    }

    /** Opens a cycle Draft for an explicit range on an already locked client and records its end. */
    public function openRange(Client $locked, CarbonImmutable $start, CarbonImmutable $end, ?Client $sync = null): Invoice
    {
        $organization = $locked->organization;
        $terms = $locked->payment_terms_days ?? $organization->billingSettings()->payment_terms_days;

        $invoice = $this->createInvoice->handle($organization, $locked, [
            'service_period_start' => $start->toDateString(),
            'service_period_end' => $end->toDateString(),
            'issue_date' => $end->toDateString(),
            'due_date' => $end->addDays($terms)->toDateString(),
            'contact_id' => $locked->billing_contact_id,
            'cc_emails' => $locked->billing_cc_emails,
            'closes_at' => CarbonImmutable::parse($end->toDateString().' 23:59:00', $organization->billingTimezone())->utc(),
            'sprint_id' => $this->matchingSprintId($locked, $start, $end),
        ]);

        $locked->update(['billing_opened_through' => $end->toDateString()]);
        $sync?->setRawAttributes($locked->getAttributes(), true);

        return $invoice;
    }

    private function matchingSprintId(Client $client, CarbonImmutable $start, CarbonImmutable $end): ?int
    {
        $organization = $client->organization;

        return $organization->allSprints()
            ->whereDate('starts_at', $start->toDateString())
            ->whereDate('ends_at', $end->toDateString())
            ->get()
            ->sortBy(fn (Sprint $sprint): int => $sprint->owner_type === $organization->getMorphClass() ? 0 : 1)
            ->first()?->id;
    }
}
