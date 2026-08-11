<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Karl Murray',
            'email' => 'karl@vheissulabs.com',
        ]);

        $this->call([
            OrganizationSeeder::class,
            ClientSeeder::class,
            ContactSeeder::class,
            TeamSeeder::class,
            NotificationSeeder::class,
        ]);
    }
}
