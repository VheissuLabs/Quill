<?php

namespace Database\Seeders;

use App\Models\EmailAddress;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Extra sign-in addresses: the test user gets one verified and one still pending, and a few other
 * people have a verified alias.
 */
class EmailAddressSeeder extends Seeder
{
    public function run(): void
    {
        $testUser = User::where('email', 'test@example.com')->firstOrFail();

        EmailAddress::factory()->for($testUser)->verified()->create();
        EmailAddress::factory()->for($testUser)->create();

        User::where('email', '!=', 'test@example.com')->inRandomOrder()->take(5)->get()
            ->each(fn (User $user) => EmailAddress::factory()->for($user)->verified()->create());
    }
}
