<?php

namespace Database\Factories;

use App\Enums\SprintStatus;
use App\Models\Sprint;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sprint>
 */
class SprintFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_type' => (new Team)->getMorphClass(),
            'owner_id' => Team::factory(),
            'name' => 'Sprint '.fake()->unique()->numberBetween(1, 999),
            'starts_at' => now()->startOfWeek(),
            'ends_at' => now()->startOfWeek()->addWeeks(2)->subDay(),
            'status' => SprintStatus::Planned,
        ];
    }

    /**
     * Indicate that the sprint is running.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SprintStatus::Active,
        ]);
    }

    /**
     * Indicate that the sprint has finished.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SprintStatus::Completed,
            'completed_at' => now(),
        ]);
    }
}
