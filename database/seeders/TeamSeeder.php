<?php

namespace Database\Seeders;

use App\Models\Organization;
use Illuminate\Database\Seeder;

/**
 * Two or three departments per organization. The team observer gives each one its default board.
 */
class TeamSeeder extends Seeder
{
    public function run(): void
    {
        $departments = ['Engineering', 'Design', 'Support', 'Marketing', 'Operations'];

        Organization::all()->each(function (Organization $organization) use ($departments) {
            foreach (fake()->randomElements($departments, fake()->numberBetween(2, 3)) as $name) {
                $organization->teams()->create(['name' => $name]);
            }
        });
    }
}
