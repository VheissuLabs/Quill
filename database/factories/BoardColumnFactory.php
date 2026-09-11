<?php

namespace Database\Factories;

use App\Enums\IssueStatus;
use App\Models\BoardColumn;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BoardColumn>
 */
class BoardColumnFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => fake()->unique()->word(),
            'status' => IssueStatus::InProgress,
            'position' => fn (array $attributes) => (BoardColumn::where('team_id', $attributes['team_id'])->max('position') ?? -1) + 1,
        ];
    }
}
