<?php

namespace Database\Factories;

use App\Enums\FeedbackStatus;
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
            'status' => FeedbackStatus::Open,
        ];
    }
}
