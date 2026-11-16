<?php

namespace App\Actions\Billing;

use App\Enums\InvoiceStatus;
use App\Models\Client;

class StopBillingCycle
{
    public function handle(Client $client): void
    {
        $client->invoices()
            ->whereNotNull('closes_at')
            ->whereNull('sent_at')
            ->where('status', '!=', InvoiceStatus::Void)
            ->update(['closes_at' => null]);
    }
}
