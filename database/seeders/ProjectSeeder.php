<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Organization;
use App\Models\Project;
use Illuminate\Database\Seeder;

/**
 * Boards of every shape per organization: a public product board, a private board restricted to
 * one team, and a private board for each client.
 */
class ProjectSeeder extends Seeder
{
    public function run(): void
    {
        Organization::with(['teams', 'clients'])->get()->each(function (Organization $organization) {
            Project::factory()->for($organization)->public()->create();

            Project::factory()->for($organization)->create()
                ->teams()->sync([$organization->teams->first()->id]);

            $organization->clients->each(
                fn (Client $client) => Project::factory()->for($organization)->for($client)->create(),
            );
        });
    }
}
