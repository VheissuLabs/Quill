<?php

namespace Database\Seeders;

use App\Enums\TeamRole;
use App\Models\Client;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\Concerns\AttributesActivity;
use Illuminate\Database\Seeder;

class TeamSeeder extends Seeder
{
    use AttributesActivity;

    /**
     * Teams are subgroups of a client. Spread across several clients in several
     * organizations so switching organizations visibly changes the team list, and
     * so the test user holds a different role in each team.
     *
     * @var array<string, array<string, TeamRole>>
     */
    protected array $teams = [
        'Acme Title Co' => [
            'Development' => TeamRole::Owner,
            'Design' => TeamRole::Admin,
        ],
        'Harbor Escrow' => [
            'Quality Assurance' => TeamRole::Member,
        ],
        'Ridgeline Outfitters' => [
            'Platform' => TeamRole::Member,
        ],
        'Wavelength Audio' => [
            'Audio Tools' => TeamRole::Owner,
        ],
    ];

    public function run(): void
    {
        $user = User::where('email', 'karl@vheissulabs.com')->firstOrFail();

        /**
         * ClientSeeder creates the org-level "Delivery" team as structure, because
         * NotaryDash's clients hang off it. Membership belongs here, so it is
         * populated rather than created.
         */
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
                        ->create(['name' => $name]);

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
