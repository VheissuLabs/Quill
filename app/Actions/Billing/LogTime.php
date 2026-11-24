<?php

namespace App\Actions\Billing;

use App\Enums\InvoiceStatus;
use App\Enums\LineUnit;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Issue;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

class LogTime
{
    public function __construct(private OpenBillingPeriod $openBillingPeriod, private CreateInvoice $createInvoice) {}

    public function handle(Issue $issue, float $hours): InvoiceLine
    {
        $client = $issue->project->client;

        if ($client === null) {
            throw new DomainException(__(':project has no client, so its time has no invoice to go on.', ['project' => $issue->project->name]));
        }

        $client = $client->billedThrough ?? $client;

        return DB::transaction(function () use ($issue, $hours, $client): InvoiceLine {
            $invoice = $this->draftInvoiceFor($client);

            $line = $invoice->lines()
                ->where('issue_id', $issue->id)
                ->where('unit', LineUnit::Hours)
                ->lockForUpdate()
                ->first();

            if ($line) {
                $line->update(['quantity' => round((float) $line->quantity + $hours, 2)]);

                return $line;
            }

            $rate = $issue->project->billingRate() ?? $invoice->defaultLineRate();

            if ($rate === null) {
                throw new DomainException(__('Set an hourly rate on :client or in billing settings before logging time.', ['client' => $client->name]));
            }

            return $invoice->lines()->create([
                'description' => $issue->title,
                'quantity' => round($hours, 2),
                'unit' => LineUnit::Hours,
                'rate' => $rate,
                'issue_id' => $issue->id,
                'sort_order' => ($invoice->lines()->max('sort_order') ?? -1) + 1,
            ]);
        });
    }

    private function draftInvoiceFor(Client $client): Invoice
    {
        return $client->openCycleInvoice()
            ?? $this->openBillingPeriod->handle($client, CarbonImmutable::now($client->organization->billingTimezone())->startOfDay())
            ?? $client->invoices()->where('status', InvoiceStatus::Draft)->whereNull('sent_at')->latest('id')->first()
            ?? $this->createInvoice->handle($client->organization, $client, []);
    }
}
