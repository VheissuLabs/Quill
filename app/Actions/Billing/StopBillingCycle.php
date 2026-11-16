<?php

namespace App\Actions\Billing;

use App\Models\Client;

class StopBillingCycle
{
    public function handle(Client $client): void
    {
        $client->openCycleInvoice()?->update(['closes_at' => null]);
    }
}
