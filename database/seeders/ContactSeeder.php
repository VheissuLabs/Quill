<?php

namespace Database\Seeders;

use App\Enums\OrganizationRole;
use App\Models\Client;
use App\Models\User;
use Database\Seeders\Concerns\AttributesActivity;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ContactSeeder extends Seeder
{
    use AttributesActivity;

    /** @var array<string, array<int, string>> */
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

            $this->causedBy($this->ownerOf($client->organization), function () use ($client, $people) {
                foreach ($people as $name) {
                    $contact = User::factory()->create([
                        'name' => $name,
                        'email' => Str::slug($name, '.').'@'.Str::slug($client->name).'.test',
                    ]);

                    $client->organization->members()->attach($contact, [
                        'client_id' => $client->id,
                    ]);

                    $contact->assignOrganizationRole($client->organization, OrganizationRole::Client);
                }
            });
        }
    }
}
