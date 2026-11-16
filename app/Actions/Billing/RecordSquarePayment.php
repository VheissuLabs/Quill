<?php

namespace App\Actions\Billing;

use App\Enums\PaymentMethod;
use App\Enums\SquareCheckoutStatus;
use App\Models\InvoicePayment;
use App\Models\Organization;
use App\Models\SquareCheckout;
use App\Notifications\InvoicePaymentReceived;
use App\Support\BillingEligibility;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class RecordSquarePayment
{
    public function __construct(private BillingEligibility $managers) {}

    /** @param array<string, mixed> $payment */
    public function handle(Organization $organization, array $payment): ?InvoicePayment
    {
        $paymentId = (string) Arr::get($payment, 'id', '');
        $orderId = (string) Arr::get($payment, 'order_id', '');

        if ($paymentId === '' || $orderId === '') {
            return null;
        }

        try {
            [$recorded, $isNew] = DB::transaction(fn (): array => $this->record($organization, $payment, $paymentId, $orderId));
        } catch (UniqueConstraintViolationException) {
            return InvoicePayment::query()->where('square_payment_id', $paymentId)->first();
        }

        if ($recorded !== null && $isNew) {
            Notification::send($this->managers->for($organization), new InvoicePaymentReceived($recorded));
        }

        return $recorded;
    }

    /**
     * @param  array<string, mixed>  $payment
     * @return array{0: ?InvoicePayment, 1: bool}
     */
    private function record(Organization $organization, array $payment, string $paymentId, string $orderId): array
    {
        $checkout = SquareCheckout::query()
            ->where('order_id', $orderId)
            ->whereHas('invoice', fn ($query) => $query->where('organization_id', $organization->id))
            ->lockForUpdate()
            ->first();

        if ($checkout === null) {
            return [null, false];
        }

        $existing = InvoicePayment::query()->where('square_payment_id', $paymentId)->first();

        if ($checkout->status === SquareCheckoutStatus::Paid) {
            return [$existing, false];
        }

        $status = Arr::get($payment, 'status');

        if ($status === 'PENDING') {
            $checkout->update(['status' => SquareCheckoutStatus::Pending, 'square_payment_id' => $paymentId]);

            return [null, false];
        }

        if ($status !== 'COMPLETED') {
            if (in_array($status, ['FAILED', 'CANCELED'], true)
                && $checkout->status === SquareCheckoutStatus::Pending
                && $checkout->square_payment_id === $paymentId) {
                $checkout->update(['status' => SquareCheckoutStatus::Open]);
            }

            return [null, false];
        }

        $checkout->update(['status' => SquareCheckoutStatus::Paid, 'square_payment_id' => $paymentId]);

        if ($existing !== null) {
            return [$existing, false];
        }

        $cents = (int) Arr::get($payment, 'amount_money.amount', 0);

        $recorded = $checkout->invoice->payments()->create([
            'amount' => sprintf('%d.%02d', intdiv($cents, 100), $cents % 100),
            'paid_on' => CarbonImmutable::parse((string) Arr::get($payment, 'created_at'))->setTimezone($organization->billingTimezone())->toDateString(),
            'method' => $this->method($payment),
            'reference' => $paymentId,
            'square_payment_id' => $paymentId,
        ]);

        return [$recorded, true];
    }

    /** @param array<string, mixed> $payment */
    private function method(array $payment): PaymentMethod
    {
        return match (true) {
            Arr::get($payment, 'source_type') === 'CARD' => PaymentMethod::Card,
            Arr::get($payment, 'source_type') === 'BANK_ACCOUNT' => PaymentMethod::Ach,
            Arr::get($payment, 'source_type') === 'WALLET' && Arr::get($payment, 'wallet_details.brand') === 'CASH_APP' => PaymentMethod::CashApp,
            default => PaymentMethod::Other,
        };
    }
}
