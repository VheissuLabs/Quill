<?php

namespace Database\Factories;

use App\Models\Feature;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vote>
 */
class VoteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'feature_id' => Feature::factory(),
            'user_id' => User::factory(),
            'direction' => 1,
        ];
    }

    /**
     * Indicate that the vote is against the feature.
     */
    public function down(): static
    {
        return $this->state(fn (array $attributes) => [
            'direction' => -1,
        ]);
    }
}
