<?php

namespace App\Actions\Billing;

use App\Models\Client;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;

class RemoveAutomaticBilling
{
    public function handle(Organization $organization): void
    {
        DB::transaction(function () use ($organization): void {
            $organization->clients()->lockForUpdate()->get()->each(function (Client $client): void {
                $invoice = $client->openCycleInvoice();

                if ($invoice !== null) {
                    if ($invoice->lines()->doesntExist() && $invoice->payments()->doesntExist()) {
                        $invoice->delete();
                    } else {
                        $invoice->update(['closes_at' => null, 'reminded_at' => null]);
                    }
                }

                $client->update([
                    'billing_cycle' => null,
                    'billing_cycle_starts_on' => null,
                    'billing_opened_through' => null,
                ]);
            });
        });
    }
}
