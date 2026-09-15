<?php

namespace Database\Seeders;

use App\Models\Organization;
use Illuminate\Database\Seeder;

/**
 * Two or three departments per organization, each with its own board from the team observer.
 * VheissuLabs is left without any, so the organization runs the board itself and the teamless
 * side of the app has somewhere to be looked at.
 */
class TeamSeeder extends Seeder
{
    public function run(): void
    {
        $departments = ['Engineering', 'Design', 'Support', 'Marketing', 'Operations'];

        Organization::where('slug', '!=', 'vheissulabs')->get()->each(function (Organization $organization) use ($departments) {
            foreach (fake()->randomElements($departments, fake()->numberBetween(2, 3)) as $name) {
                $organization->teams()->create(['name' => $name]);
            }
        });
    }
}
