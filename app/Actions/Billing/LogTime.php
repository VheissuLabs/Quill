<?php

namespace App\Actions\Billing;

use App\Enums\InvoiceStatus;
use App\Enums\LineUnit;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Issue;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class LogTime
{
    public function __construct(private OpenBillingPeriod $openBillingPeriod, private CreateInvoice $createInvoice) {}

    public function handle(
        Client $client,
        CarbonImmutable $completedAt,
        float $hours,
        ?User $user = null,
        ?Issue $issue = null,
        ?string $description = null,
        ?string $referenceUrl = null,
        ?int $workTypeId = null,
    ): TimeEntry {
        $billedClient = $client->billedThrough ?? $client;

        return DB::transaction(function () use ($client, $completedAt, $hours, $user, $issue, $description, $referenceUrl, $workTypeId, $billedClient): TimeEntry {
            $entry = TimeEntry::query()->create([
                'client_id' => $client->id,
                'organization_id' => $client->organization_id,
                'user_id' => $user?->id,
                'issue_id' => $issue?->id,
                'completed_at' => $completedAt->toDateString(),
                'hours' => round($hours, 2),
                'description' => filled($description) ? $description : $issue?->title,
                'reference_url' => $referenceUrl,
                'work_type_id' => $workTypeId,
            ]);

            $invoice = $this->invoiceFor($billedClient, $completedAt);

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
            ->whereDate('completed_at', $entry->completed_at->toDateString())
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
            'completed_at' => $entry->completed_at->toDateString(),
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
