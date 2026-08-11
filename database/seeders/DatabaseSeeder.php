<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        /**
         * A fixed key so `migrate:fresh --seed` does not invalidate the local
         * session and log the developer out on every reseed.
         */
        User::factory()->create([
            'id' => '019ff2df-fea9-7355-8e26-6b7617008e1d',
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
