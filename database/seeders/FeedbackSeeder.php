<?php

namespace Database\Seeders;

use App\Enums\FeedbackStatus;
use App\Models\Feedback;
use App\Models\Project;
use Illuminate\Database\Seeder;

/**
 * A dozen or so requests per board, spread over the last two months and across every status.
 * Planned, in-progress and completed requests are turned into issues by IssueSeeder, which keeps
 * their status in step with the ticket.
 */
class FeedbackSeeder extends Seeder
{
    public function run(): void
    {
        Project::with('organization.members')->get()->each(function (Project $project) {
            $authors = $project->organization->members;

            Feedback::factory()
                ->count(fake()->numberBetween(8, 16))
                ->for($project)
                ->sequence(fn () => [
                    'user_id' => $authors->random()->id,
                    'status' => fake()->randomElement([
                        FeedbackStatus::Open, FeedbackStatus::Open, FeedbackStatus::Open,
                        FeedbackStatus::Planned, FeedbackStatus::InProgress,
                        FeedbackStatus::Completed, FeedbackStatus::Declined,
                    ]),
                    'title' => rtrim(fake()->sentence(fake()->numberBetween(3, 7)), '.'),
                    'description' => fake()->boolean(85) ? fake()->paragraphs(fake()->numberBetween(1, 2), true) : null,
                    'created_at' => fake()->dateTimeBetween('-60 days', '-1 day'),
                ])
                ->create();
        });
    }
}
