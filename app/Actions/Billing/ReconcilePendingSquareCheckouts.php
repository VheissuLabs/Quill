<?php

namespace App\Actions\Billing;

use App\Enums\OrganizationCapability;
use App\Enums\SquareCheckoutStatus;
use App\Models\SquareCheckout;
use App\Support\SquareClient;
use App\Support\SquareException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

class ReconcilePendingSquareCheckouts
{
    public function __construct(private RecordSquarePayment $recordPayment) {}

    public function handle(CarbonImmutable $now): void
    {
        SquareCheckout::query()
            ->where('status', SquareCheckoutStatus::Pending)
            ->whereNotNull('square_payment_id')
            ->where(fn ($query) => $query->whereNull('checked_at')->orWhere('checked_at', '<=', $now->subHour()))
            ->with('invoice.organization')
            ->get()
            ->each(function (SquareCheckout $checkout) use ($now): void {
                $organization = $checkout->invoice->organization;
                $settings = $organization->billingSettings();

                if (! $organization->canUse(OrganizationCapability::Invoices) || ! $settings->squareConfigured()) {
                    return;
                }

                $checkout->update(['checked_at' => $now]);

                try {
                    $payment = SquareClient::for($settings)->retrievePayment((string) $checkout->square_payment_id);

                    $this->recordPayment->handle($organization, $payment);
                } catch (SquareException $exception) {
                    Log::warning('Square pending payment could not be checked.', [
                        'checkout_id' => $checkout->id,
                        'payment_id' => $checkout->square_payment_id,
                        'error' => $exception->getMessage(),
                    ]);
                }
            });
    }
}
