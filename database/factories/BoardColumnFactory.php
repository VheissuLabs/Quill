<?php

namespace Database\Factories;

use App\Enums\IssueStatus;
use App\Models\BoardColumn;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BoardColumn> */
class BoardColumnFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_type' => (new Team)->getMorphClass(),
            'owner_id' => Team::factory(),
            'name' => fake()->unique()->word(),
            'status' => IssueStatus::InProgress,
            'position' => fn (array $attributes) => (BoardColumn::where('owner_type', $attributes['owner_type'])->where('owner_id', $attributes['owner_id'])->max('position') ?? -1) + 1,
        ];
    }
}
