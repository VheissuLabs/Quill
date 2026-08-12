<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Organization;
use App\Models\Team;
use Database\Seeders\Concerns\AttributesActivity;
use Database\Seeders\Concerns\NamesDepartments;
use Illuminate\Database\Seeder;

class ClientSeeder extends Seeder
{
    use AttributesActivity, NamesDepartments;

    /**
     * The companies each organization does work for. Named rather than faked so
     * the seeded app reads like the real thing when clicking through it.
     *
     * NotaryDash holds its clients through a "Delivery" team, which is the
     * org-team-owns-clients shape; the other two hold theirs directly. Both
     * arrangements are legal and the seed shows each.
     *
     * @var array<string, array<int, string>>
     */
    protected array $clients = [
        'NotaryDash' => ['Acme Title Co', 'Harbor Escrow', 'Sunbelt Signings'],
        '92 Labs' => ['Ridgeline Outfitters', 'Copperfield Dental'],
        'VheissuLabs' => ['Wavelength Audio'],
    ];

    public function run(): void
    {
        foreach ($this->clients as $organizationName => $clientNames) {
            $organization = Organization::where('name', $organizationName)->firstOrFail();

            $this->causedBy($this->ownerOf($organization), function () use ($organization, $organizationName, $clientNames) {
                $parent = $organizationName === 'NotaryDash'
                    ? Team::factory()->heldBy($organization)->create(['name' => $this->department('Delivery')])
                    : $organization;

                foreach ($clientNames as $name) {
                    Client::factory()->heldBy($parent)->create(['name' => $name]);
                }
            });
        }
    }
}
