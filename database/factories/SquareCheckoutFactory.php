<?php

namespace Database\Factories;

use App\Enums\SquareCheckoutStatus;
use App\Models\Invoice;
use App\Models\SquareCheckout;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SquareCheckout> */
class SquareCheckoutFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'payment_link_id' => fake()->unique()->bothify('LINK-????####'),
            'order_id' => fake()->unique()->bothify('ORDER-????####'),
            'url' => fake()->url(),
            'amount' => fake()->randomFloat(2, 10, 5000),
            'status' => SquareCheckoutStatus::Open,
        ];
    }
}
