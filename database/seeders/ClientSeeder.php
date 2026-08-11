<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Organization;
use Illuminate\Database\Seeder;

class ClientSeeder extends Seeder
{
    /**
     * The companies each organization does work for. Named rather than faked so
     * the seeded app reads like the real thing when clicking through it.
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

            foreach ($clientNames as $name) {
                Client::factory()->for($organization)->create(['name' => $name]);
            }
        }
    }
}
