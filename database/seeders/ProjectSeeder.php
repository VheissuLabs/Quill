<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Project;
use App\Models\Team;
use Database\Seeders\Concerns\AttributesActivity;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    use AttributesActivity;

    /**
     * Sunbelt Signings is absent on purpose — a client not yet set up is a real state.
     *
     * @var array<string, string>
     */
    protected array $clientProjects = [
        'Acme Title Co' => 'Acme Website',
        'Harbor Escrow' => 'Harbor Escrow Portal',
        'Ridgeline Outfitters' => 'Ridgeline Storefront',
        'Copperfield Dental' => 'Copperfield Booking',
        'Wavelength Audio' => 'Wavelength App',
    ];

    /**
     * Internal work that belongs to no client, so both owner types appear on screen.
     *
     * @var array<string, string>
     */
    protected array $teamProjects = [
        'Delivery' => 'Delivery Internal Tooling',
    ];

    public function run(): void
    {
        foreach ($this->clientProjects as $clientName => $projectName) {
            $client = Client::where('name', $clientName)->firstOrFail();

            $this->causedBy($this->ownerOf($client->organization), function () use ($client, $projectName) {
                $project = Project::factory()->ownedBy($client)->create(['name' => $projectName]);

                $client->update(['default_project_id' => $project->id]);
            });
        }

        foreach ($this->teamProjects as $teamName => $projectName) {
            $team = Team::where('name', $teamName)->firstOrFail();

            $this->causedBy($this->ownerOf($team->organization), function () use ($team, $projectName) {
                Project::factory()->ownedBy($team)->create(['name' => $projectName]);
            });
        }
    }
}
