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
     * One project per client, owned by that client, and that client's work lands
     * there. This is the ordinary case, and keeping it uniform means anything that
     * differs on screen differs for a reason.
     *
     * Sunbelt Signings is absent on purpose: a client that has not been set up yet
     * is a real state, and it is the only row with no default project.
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
     * A team may own a project too — internal work that belongs to no client.
     *
     * Exactly one exists, and no client points at it, so the difference between
     * "a client's project" and "a team's project" is visible on screen rather
     * than something you have to read the seeder to notice.
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
