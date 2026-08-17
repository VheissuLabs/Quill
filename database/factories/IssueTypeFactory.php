<?php

namespace Database\Factories;

use App\Models\IssueType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<IssueType> */
class IssueTypeFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->word()),
            'position' => 0,
        ];
    }
}
