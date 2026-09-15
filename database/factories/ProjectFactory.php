<?php

namespace Database\Factories;

use App\Enums\ProjectVisibility;
use App\Models\Client;
use App\Models\Organization;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'client_id' => null,
            'name' => Str::headline(fake()->unique()->slug(2)),
            'description' => fake()->sentence(),
            'visibility' => ProjectVisibility::Private,
        ];
    }

    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => ProjectVisibility::Public,
        ]);
    }

    public function forClient(): static
    {
        return $this->state(fn (array $attributes) => [
            'client_id' => Client::factory()->state(fn () => ['organization_id' => $attributes['organization_id']]),
        ]);
    }
}
