<?php

namespace Database\Seeders;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

class TeamSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'test@example.com')->firstOrFail();

        /**
         * One team per role the test user can hold, so every permission path has
         * somewhere to be exercised without editing the database by hand.
         *
         * These teams are not yet attached to an organization — that arrives with
         * the reparenting in PR 2, which is also when their names start meaning
         * "department of NotaryDash" rather than standing alone.
         */
        $teams = [
            'Development' => TeamRole::Owner,
            'Design' => TeamRole::Admin,
            'Quality Assurance' => TeamRole::Member,
        ];

        foreach ($teams as $name => $role) {
            $team = Team::factory()
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
    }
}
