<?php

namespace Database\Factories;

use App\Enums\FeatureStatus;
use App\Models\Feature;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Feature> */
class FeatureFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'user_id' => User::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'status' => FeatureStatus::Open,
        ];
    }
}
