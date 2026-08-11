<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::where('email', 'test@example.com')->firstOrFail();

        $organizations = collect(['NotaryDash', '92 Labs', 'VheissuLabs'])
            ->map(fn (string $name) => Organization::factory()
                ->withOwner($owner)
                ->withMembers(2)
                ->withClientContact()
                ->create(['name' => $name]),
            );

        /**
         * Land the user in a real organization rather than the empty personal one
         * they were given at registration, so a freshly seeded app has something
         * to show on the first page load.
         */
        $owner->switchOrganization($organizations->first());
    }
}
