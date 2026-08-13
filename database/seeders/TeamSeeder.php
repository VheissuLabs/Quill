<?php

namespace Database\Seeders;

use App\Enums\TeamRole;
use App\Models\Client;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\Concerns\AttributesActivity;
use Database\Seeders\Concerns\NamesDepartments;
use Illuminate\Database\Seeder;

class TeamSeeder extends Seeder
{
    use AttributesActivity, NamesDepartments;

    /** @var array<string, array<string, TeamRole>> */
    protected array $teams = [
        'Acme Title Co' => [
            'Engineering' => TeamRole::Owner,
            'Design' => TeamRole::Admin,
        ],
        'Harbor Escrow' => [
            'Quality Assurance' => TeamRole::Member,
        ],
        'Ridgeline Outfitters' => [
            'Client Services' => TeamRole::Member,
        ],
        'Wavelength Audio' => [
            'Support' => TeamRole::Owner,
        ],
    ];

    public function run(): void
    {
        $user = User::where('email', 'karl@vheissulabs.com')->firstOrFail();

        $delivery = Team::where('name', 'Delivery')->firstOrFail();
        $delivery->members()->attach($user, ['role' => TeamRole::Admin->value]);
        User::factory()->count(2)->create()->each(
            fn (User $member) => $delivery->members()->attach($member, ['role' => TeamRole::Member->value]),
        );
        $delivery->members()->attach(User::factory()->create(), ['role' => TeamRole::Owner->value]);

        foreach ($this->teams as $clientName => $teams) {
            $client = Client::where('name', $clientName)->firstOrFail();

            $this->causedBy($this->ownerOf($client->organization), function () use ($client, $teams, $user) {
                foreach ($teams as $name => $role) {
                    $team = Team::factory()
                        ->heldBy($client)
                        ->withMember($user, $role)
                        ->withMembers(2)
                        ->create(['name' => $this->department($name)]);

                    if ($role !== TeamRole::Owner) {
                        $team->members()->attach(
                            User::factory()->create(),
                            ['role' => TeamRole::Owner->value],
                        );
                    }
                }
            });
        }
    }
}
