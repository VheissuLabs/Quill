<?php

namespace Database\Factories;

use App\Models\Feedback;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Vote> */
class VoteFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'feedback_id' => Feedback::factory(),
            'user_id' => User::factory(),
            'direction' => 1,
        ];
    }

    public function down(): static
    {
        return $this->state(fn (array $attributes) => [
            'direction' => -1,
        ]);
    }
}
