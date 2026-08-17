<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\Concerns\AttributesActivity;
use Database\Seeders\Concerns\NamesDepartments;
use Illuminate\Database\Seeder;

class TeamSeeder extends Seeder
{
    use AttributesActivity, NamesDepartments;

    /**
     * The departments working on each client's account, and whether the test user
     * owns that team or merely belongs to it.
     *
     * @var array<string, array<string, bool>>
     */
    protected array $teams = [
        'Acme Title Co' => [
            'Engineering' => true,
            'Design' => false,
        ],
        'Harbor Escrow' => [
            'Quality Assurance' => false,
        ],
        'Ridgeline Outfitters' => [
            'Client Services' => false,
        ],
        'Wavelength Audio' => [
            'Support' => true,
        ],
    ];

    public function run(): void
    {
        $user = User::where('email', 'karl@vheissulabs.com')->firstOrFail();

        $delivery = Team::where('name', 'Delivery')->firstOrFail();
        $delivery->members()->attach($user);
        User::factory()->count(2)->create()->each(
            fn (User $member) => $delivery->members()->attach($member),
        );

        foreach ($this->teams as $clientName => $teams) {
            $client = Client::where('name', $clientName)->firstOrFail();

            $this->causedBy(
                $this->ownerOf($client->organization),
                fn () => collect($teams)->each(
                    fn (bool $ownedByUser, string $name) => $this->seedTeam($client, $name, $ownedByUser, $user)
                ),
            );
        }
    }

    protected function seedTeam(Client $client, string $name, bool $ownedByUser, User $user): void
    {
        $team = Team::factory()
            ->heldBy($client)
            ->withMember($user)
            ->withMembers(2)
            ->create(['name' => $this->department($name)]);

        $owner = $ownedByUser ? $user : User::factory()->create();

        $team->update(['owner_id' => $owner->id]);

        if (! $ownedByUser) {
            $team->members()->attach($owner);
        }
    }
}
