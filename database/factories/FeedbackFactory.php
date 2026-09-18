<?php

namespace Database\Factories;

use App\Enums\FeedbackStatus;
use App\Enums\FeedbackType;
use App\Models\Feedback;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Feedback> */
class FeedbackFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'user_id' => User::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'type' => FeedbackType::Feature,
            'status' => FeedbackStatus::Open,
        ];
    }

    public function bug(): static
    {
        return $this->state(fn () => ['type' => FeedbackType::Bug]);
    }
}
