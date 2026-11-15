<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InvoicePayment> */
class InvoicePaymentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'amount' => '100.00',
            'paid_on' => now()->toDateString(),
            'method' => PaymentMethod::Check,
        ];
    }
}
