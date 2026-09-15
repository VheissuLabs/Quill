<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * A known login for clicking around locally plus a crowd of generated people to fill the
 * organizations. Everyone signs in with "password".
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create(['name' => 'Test User', 'email' => 'test@example.com']);

        User::factory()->count(18)->create();
    }
}
