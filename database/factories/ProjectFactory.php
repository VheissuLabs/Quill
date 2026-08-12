<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Project> */
class ProjectFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->unique()->company().' Delivery',
            'description' => null,
        ];
    }

    public function ownedBy(Client|Team $owner): static
    {
        return $this->state(fn (array $attributes) => [
            'organization_id' => $owner->organization_id,
            'owner_type' => $owner::class,
            'owner_id' => $owner->id,
        ]);
    }

    public function trashed(): static
    {
        return $this->state(fn (array $attributes) => [
            'deleted_at' => now(),
        ]);
    }
}
