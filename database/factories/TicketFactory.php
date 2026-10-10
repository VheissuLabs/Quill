<?php

namespace Database\Factories;

use App\Enums\TicketStatus;
use App\Models\Client;
use App\Models\Organization;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Ticket> */
class TicketFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'subject' => fake()->sentence(5),
            'status' => TicketStatus::Open,
            'requester_name' => fake()->name(),
            'requester_email' => fake()->unique()->safeEmail(),
        ];
    }

    public function forClient(Client $client): static
    {
        return $this->state(fn (array $attributes) => [
            'organization_id' => $client->organization_id,
            'client_id' => $client->getKey(),
        ]);
    }
}
