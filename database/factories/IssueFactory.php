<?php

namespace Database\Factories;

use App\Enums\IssuePriority;
use App\Enums\IssueStatus;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Issue> */
class IssueFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'created_by' => User::factory(),
            'number' => fn (array $attributes) => (Issue::where('project_id', $attributes['project_id'])->max('number') ?? 0) + 1,
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'status' => IssueStatus::Backlog,
            'priority' => IssuePriority::None,
        ];
    }
}
