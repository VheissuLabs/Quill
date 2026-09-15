<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Organization;
use Illuminate\Database\Seeder;

/** One or two customers per organization whose boards stay separate from the organization's own. */
class ClientSeeder extends Seeder
{
    public function run(): void
    {
        Organization::all()->each(
            fn (Organization $organization) => Client::factory()->for($organization)->count(fake()->numberBetween(1, 2))->create(),
        );
    }
}
