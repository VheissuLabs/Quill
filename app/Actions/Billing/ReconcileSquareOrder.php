<?php

namespace App\Actions\Billing;

use App\Enums\SquareCheckoutStatus;
use App\Models\Invoice;
use App\Support\SquareClient;
use App\Support\SquareException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class ReconcileSquareOrder
{
    public function __construct(private RecordSquarePayment $recordPayment) {}

    public function handle(Invoice $invoice, string $orderId): void
    {
        $isUnsettledCheckout = $invoice->squareCheckouts()
            ->where('order_id', $orderId)
            ->whereIn('status', [SquareCheckoutStatus::Open, SquareCheckoutStatus::Pending])
            ->exists();

        if (! $isUnsettledCheckout) {
            return;
        }

        $organization = $invoice->organization;
        $client = SquareClient::for($organization->billingSettings());

        try {
            $paymentIds = array_filter(Arr::pluck(Arr::wrap(Arr::get($client->retrieveOrder($orderId), 'tenders', [])), 'id'));

            foreach ($paymentIds as $paymentId) {
                $this->recordPayment->handle($organization, $client->retrievePayment((string) $paymentId));
            }
        } catch (SquareException $exception) {
            Log::warning('Square order could not be reconciled.', ['invoice_id' => $invoice->id, 'order_id' => $orderId, 'error' => $exception->getMessage()]);
        }
    }
}
