<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\ClientDomain;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ClientDomain> */
class ClientDomainFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'domain' => fake()->unique()->domainName(),
        ];
    }
}
