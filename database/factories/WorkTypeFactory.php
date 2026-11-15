<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\WorkType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WorkType> */
class WorkTypeFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->unique()->words(2, true),
            'color' => 'gray',
            'is_active' => true,
        ];
    }
}
