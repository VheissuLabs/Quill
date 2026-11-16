<?php

namespace App\Actions\Billing;

use App\Enums\SquareCheckoutStatus;
use App\Models\Invoice;
use App\Models\SquareCheckout;
use App\Support\SquareClient;
use App\Support\SquareException;

class StartSquareCheckout
{
    public function __construct(private readonly CancelSquareCheckouts $cancelCheckouts) {}

    /** @throws SquareException */
    public function handle(Invoice $invoice): SquareCheckout
    {
        throw_unless($invoice->canPayOnline(), SquareException::class, 'This invoice cannot be paid online.');

        $this->cancelCheckouts->handle($invoice);

        $amountDue = $invoice->amountDue();

        $link = SquareClient::for($invoice->organization->billingSettings())->createPaymentLink(
            "Invoice {$invoice->number}",
            (int) round((float) $amountDue * 100),
            $invoice->publicUrl(),
            $invoice->number,
        );

        return $invoice->squareCheckouts()->create([
            'payment_link_id' => $link['id'],
            'order_id' => $link['order_id'],
            'url' => $link['url'],
            'amount' => $amountDue,
            'status' => SquareCheckoutStatus::Open,
        ]);
    }
}
