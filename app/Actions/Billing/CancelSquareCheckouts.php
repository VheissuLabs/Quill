<?php

namespace App\Actions\Billing;

use App\Enums\SquareCheckoutStatus;
use App\Models\Invoice;
use App\Models\SquareCheckout;
use App\Support\SquareClient;
use App\Support\SquareException;

class CancelSquareCheckouts
{
    public function handle(Invoice $invoice): void
    {
        $open = $invoice->squareCheckouts()->where('status', SquareCheckoutStatus::Open)->get();

        if ($open->isEmpty()) {
            return;
        }

        $client = SquareClient::for($invoice->organization->billingSettings());

        $open->each(function (SquareCheckout $checkout) use ($client): void {
            $checkout->update(['status' => SquareCheckoutStatus::Cancelled]);

            try {
                $client->deletePaymentLink($checkout->payment_link_id);
            } catch (SquareException $exception) {
                return 'Unable to delete the payment link: ';
            }
        });
    }
}
