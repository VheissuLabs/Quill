<?php

namespace App\Actions\Billing;

use App\Enums\InvoiceStatus;
use App\Models\BillingSetting;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Organization;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CreateInvoice
{
    /** @param  array<string, mixed>  $attributes */
    public function handle(Organization $organization, Client $client, array $attributes): Invoice
    {
        $organization->billingSettings();

        return DB::transaction(function () use ($organization, $client, $attributes): Invoice {
            $settings = BillingSetting::where('organization_id', $organization->id)->lockForUpdate()->firstOrFail();
            $issueDate = Carbon::parse($attributes['issue_date'] ?? today());

            return Invoice::create([
                'issue_date' => $issueDate->toDateString(),
                'due_date' => $issueDate->copy()->addDays($client->payment_terms_days ?? $settings->payment_terms_days)->toDateString(),
                'contact_id' => $client->billing_contact_id,
                'cc_emails' => $client->billing_cc_emails,
                'tax_rate' => $settings->tax_rate,
                ...$attributes,
                'organization_id' => $organization->id,
                'client_id' => $client->id,
                'number' => $this->nextNumber($organization, $settings->invoice_prefix),
                'status' => InvoiceStatus::Draft,
            ]);
        });
    }

    private function nextNumber(Organization $organization, string $prefix): string
    {
        $series = $prefix.'-'.now()->year.'-';

        $highest = Invoice::where('organization_id', $organization->id)
            ->where('number', 'like', $series.'%')
            ->pluck('number')
            ->map(fn (string $number): int => (int) substr($number, strlen($series)))
            ->max() ?? 0;

        return $series.str_pad((string) ($highest + 1), 4, '0', STR_PAD_LEFT);
    }
}
