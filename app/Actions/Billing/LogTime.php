<?php

namespace App\Actions\Billing;

use App\Enums\InvoiceStatus;
use App\Enums\LineUnit;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Issue;
use App\Models\TimeEntry;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class LogTime
{
    public function __construct(private OpenBillingPeriod $openBillingPeriod, private CreateInvoice $createInvoice) {}

    public function handle(Client $client, array $attributes): TimeEntry
    {
        $issue = Issue::query()
            ->with('project')
            ->find($attributes['issue_id'] ?? null);

        $billedClient = $client->billedThrough ?? $client;

        return DB::transaction(function () use ($client, $attributes, $issue, $billedClient): TimeEntry {
            $entry = TimeEntry::query()->create([
                ...$attributes,
                'description' => filled($attributes['description'] ?? null) ? $attributes['description'] : $issue?->title,
                'hours' => round((float) $attributes['hours'], 2),
                'organization_id' => $client->organization_id,
                'client_id' => $client->id,
            ]);

            $invoice = $this->invoiceFor($billedClient, $entry->worked_on);

            if ($invoice === null) {
                return $entry;
            }

            $line = $this->addToLine($invoice, $entry, $issue);

            $entry->update(['invoice_line_id' => $line->id]);

            return $entry;
        });
    }

    private function invoiceFor(Client $client, CarbonImmutable $day): ?Invoice
    {
        $covering = $client->invoiceCovering($day);

        if ($covering !== null) {
            return $covering->isEditable() ? $covering : null;
        }

        $today = CarbonImmutable::now($client->organization->billingTimezone())->startOfDay();

        if ($this->openBillingPeriod->handle($client, $today) !== null) {
            $opened = $client->invoiceCovering($day);

            return $opened?->isEditable() ? $opened : null;
        }

        $draft = $client->invoices()
            ->where('status', InvoiceStatus::Draft)
            ->whereNull('sent_at')
            ->whereNull('closes_at')
            ->latest('id')
            ->first()
            ?? $this->createInvoice->handle($client->organization, $client, []);

        $draft->update([
            'service_period_start' => $draft->service_period_start === null || $day->lt($draft->service_period_start) ? $day->toDateString() : $draft->service_period_start,
            'service_period_end' => $draft->service_period_end === null || $day->gt($draft->service_period_end) ? $day->toDateString() : $draft->service_period_end,
        ]);

        return $draft;
    }

    private function addToLine(Invoice $invoice, TimeEntry $entry, ?Issue $issue): InvoiceLine
    {
        $line = $invoice->lines()
            ->where('unit', LineUnit::Hours)
            ->where(fn (Builder $query) => match (true) {
                $issue !== null => $query->where('issue_id', $issue->id),
                filled($entry->reference_url) => $query->where('reference_url', $entry->reference_url),
                default => $query
                    ->whereNull('issue_id')
                    ->whereNull('reference_url')
                    ->where('description', $entry->description)
                    ->where('work_type_id', $entry->work_type_id),
            })
            ->lockForUpdate()
            ->first();

        if ($line !== null) {
            $line->update(['quantity' => round((float) $line->quantity + (float) $entry->hours, 2)]);

            return $line;
        }

        $rate = $issue?->project->billingRate() ?? $invoice->defaultLineRate();

        if ($rate === null) {
            throw new DomainException(__('Set an hourly rate on :client or in billing settings before logging time.', ['client' => $invoice->client->name]));
        }

        return $invoice->addLine([
            'description' => $entry->description,
            'quantity' => $entry->hours,
            'unit' => LineUnit::Hours,
            'rate' => $rate,
            'issue_id' => $issue?->id,
            'reference_url' => $entry->reference_url,
            'work_type_id' => $entry->work_type_id,
        ]);
    }
}
