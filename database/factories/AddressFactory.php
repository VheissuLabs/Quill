<?php

namespace Database\Factories;

use App\Enums\AddressType;
use App\Models\Address;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Address> */
class AddressFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'addressable_type' => Client::class,
            'addressable_id' => Client::factory(),
            'type' => AddressType::Billing,
            'line1' => fake()->streetAddress(),
            'line2' => null,
            'city' => fake()->city(),
            'region' => fake()->randomElement(['TX', 'CA', 'NY']),
            'postal_code' => fake()->postcode(),
            'country' => 'United States',
        ];
    }
}
