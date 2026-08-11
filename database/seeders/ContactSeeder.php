<?php

namespace Database\Seeders;

use App\Enums\OrganizationRole;
use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ContactSeeder extends Seeder
{
    /**
     * The people at each client, by client name.
     *
     * Named rather than faked so the seeded app reads like the real thing, and
     * so "who are the contacts at Acme Title Co?" has a checkable answer.
     *
     * Acme Title Co has two contacts and Sunbelt Signings has none on purpose:
     * both the several-contacts and the no-contacts-yet cases should be visible
     * without editing the seeder.
     *
     * @var array<string, array<int, string>>
     */
    protected array $contacts = [
        'Acme Title Co' => ['Lucy Alvarez', 'Marcus Webb'],
        'Harbor Escrow' => ['Priya Raman'],
        'Sunbelt Signings' => [],
        'Ridgeline Outfitters' => ['Dana Cole'],
        'Copperfield Dental' => ['Owen Pike'],
        'Wavelength Audio' => ['Sasha Boone'],
    ];

    public function run(): void
    {
        foreach ($this->contacts as $clientName => $people) {
            $client = Client::where('name', $clientName)->firstOrFail();

            foreach ($people as $name) {
                $contact = User::factory()->create([
                    'name' => $name,
                    'email' => Str::slug($name, '.').'@'.Str::slug($clientName).'.test',
                ]);

                $client->organization->members()->attach($contact, [
                    'client_id' => $client->id,
                ]);

                $contact->assignOrganizationRole($client->organization, OrganizationRole::Client);
            }
        }
    }
}
