<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Every member of an organization sits on at least one of its teams. The test user is placed on the
 * first team of each organization and has it as their current team. Organizations without teams
 * are skipped; their members work off the organization's own board.
 */
class TeamMembershipSeeder extends Seeder
{
    public function run(): void
    {
        $testUser = User::where('email', 'test@example.com')->firstOrFail();

        Organization::with(['teams', 'members'])->get()
            ->reject(fn (Organization $organization) => $organization->teams->isEmpty())
            ->each(function (Organization $organization) use ($testUser) {
                $teams = $organization->teams;

                $organization->members->each(function (User $member) use ($teams) {
                    $teams->random(fake()->numberBetween(1, min(2, $teams->count())))
                        ->each(fn (Team $team) => $team->members()->syncWithoutDetaching([$member->id]));
                });

                $teams->first()->members()->syncWithoutDetaching([$testUser->id]);
            });

        $currentTeam = $testUser->currentOrganization?->teams()->orderBy('id')->first();

        if ($currentTeam) {
            $testUser->switchTeam($currentTeam);
        }
    }
}
