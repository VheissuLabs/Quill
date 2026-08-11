<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Organization;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Client> */
class ClientFactory extends Factory
{
    /**
     * A client defaults to being held directly by its organization. Use
     * `heldBy()` to nest it under a team instead.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'parent_type' => Organization::class,
            'parent_id' => fn (array $attributes) => $attributes['organization_id'],
            'name' => fake()->unique()->company(),
        ];
    }

    public function heldBy(Organization|Team $parent): static
    {
        return $this->state(fn (array $attributes) => [
            'organization_id' => $parent instanceof Organization
                ? $parent->id
                : $parent->organization_id,
            'parent_type' => $parent::class,
            'parent_id' => $parent->id,
        ]);
    }

    public function trashed(): static
    {
        return $this->state(fn (array $attributes) => [
            'deleted_at' => now(),
        ]);
    }
}
