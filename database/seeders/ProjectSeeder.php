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
     * The work each client has on the go, and who owns it.
     *
     * Acme's two projects show a client owning its own work directly. Harbor's
     * sits under the Delivery team, which is the other legal arrangement. Sunbelt
     * has none, so the no-projects-yet case is visible without editing seeders.
     *
     * @var array<string, array<int, string>>
     */
    protected array $clientProjects = [
        'Acme Title Co' => ['Acme Website', 'Acme Closing Portal'],
        'Ridgeline Outfitters' => ['Ridgeline Storefront'],
        'Copperfield Dental' => ['Copperfield Booking'],
        'Wavelength Audio' => ['Wavelength App'],
    ];

    /** @var array<string, array<int, string>> */
    protected array $teamProjects = [
        'Delivery' => ['Harbor Escrow Rebuild'],
    ];

    public function run(): void
    {
        foreach ($this->clientProjects as $clientName => $names) {
            $client = Client::where('name', $clientName)->firstOrFail();

            $this->causedBy($this->ownerOf($client->organization), function () use ($client, $names) {
                foreach ($names as $name) {
                    $project = Project::factory()->ownedBy($client)->create(['name' => $name]);

                    /** Where a client's issues land, set once the project exists. */
                    $client->default_project_id ??= $project->id;
                }

                $client->save();
            });
        }

        foreach ($this->teamProjects as $teamName => $names) {
            $team = Team::where('name', $teamName)->firstOrFail();

            $this->causedBy($this->ownerOf($team->organization), function () use ($team, $names) {
                foreach ($names as $name) {
                    Project::factory()->ownedBy($team)->create(['name' => $name]);
                }
            });
        }

        /** Harbor's work is run by the Delivery team rather than by Harbor itself. */
        $harbor = Client::where('name', 'Harbor Escrow')->firstOrFail();
        $harbor->update([
            'default_project_id' => Project::where('name', 'Harbor Escrow Rebuild')->firstOrFail()->id,
        ]);
    }
}
