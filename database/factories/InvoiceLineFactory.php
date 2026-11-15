<?php

namespace Database\Factories;

use App\Enums\LineUnit;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InvoiceLine> */
class InvoiceLineFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'description' => fake()->sentence(4),
            'quantity' => fake()->randomElement(['1.00', '2.50', '4.00', '8.00']),
            'unit' => LineUnit::Hours,
            'rate' => '100.00',
        ];
    }
}
