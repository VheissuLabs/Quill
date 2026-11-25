<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

class TimeEntryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'organization_id' => fn (array $attributes) => Client::query()->whereKey($attributes['client_id'])->value('organization_id'),
            'worked_on' => today()->toDateString(),
            'hours' => fake()->randomElement(['0.50', '1.00', '2.00', '4.00']),
            'description' => fake()->sentence(3),
        ];
    }
}
