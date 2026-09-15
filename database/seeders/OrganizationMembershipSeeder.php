<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * The test user owns the newest organization, administers the next and is a plain member of the
 * rest, so every permission level is one switch away. Everyone else joins one or two
 * organizations at random, and each organization gets a generated owner of its own.
 */
class OrganizationMembershipSeeder extends Seeder
{
    public function run(): void
    {
        $organizations = Organization::orderByDesc('id')->get();
        $testUser = User::where('email', 'test@example.com')->firstOrFail();
        $people = User::where('email', '!=', 'test@example.com')->get();

        foreach ($organizations as $index => $organization) {
            $organization->addMember($testUser, match ($index) {
                0 => $organization->ownerRole(),
                1 => 'Admin',
                default => 'Member',
            });

            $owner = $people->pull($people->keys()->first());
            $organization->addMember($owner, $organization->ownerRole());
            $owner->switchOrganization($organization);
        }

        foreach ($people as $person) {
            foreach ($organizations->random(fake()->numberBetween(1, 2)) as $organization) {
                $organization->addMember($person, fake()->boolean(25) ? 'Admin' : 'Member');
            }

            $person->switchOrganization($person->organizations()->first());
        }

        $testUser->switchOrganization($organizations->first());
    }
}
