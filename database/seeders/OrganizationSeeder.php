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

        collect(['NotaryDash', '92 Labs', 'VheissuLabs'])
            ->each(fn (string $name) => Organization::factory()
                ->withOwner($owner)
                ->withMembers(2)
                ->withClientContact()
                ->create(['name' => $name]),
            );
    }
}
