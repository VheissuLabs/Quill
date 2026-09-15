<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PermissionsSeeder::class);

        if (app()->isProduction()) {
            return;
        }

        $this->call([
            UserSeeder::class,
            OrganizationSeeder::class,
            OrganizationMembershipSeeder::class,
            EmailAddressSeeder::class,
            OrganizationInvitationSeeder::class,
            TeamSeeder::class,
            BoardColumnSeeder::class,
            TeamMembershipSeeder::class,
            ClientSeeder::class,
            ProjectSeeder::class,
            FeatureSeeder::class,
            VoteSeeder::class,
            CommentSeeder::class,
            SprintSeeder::class,
            IssueSeeder::class,
        ]);
    }
}
