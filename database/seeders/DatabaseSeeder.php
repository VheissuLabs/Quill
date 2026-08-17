<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([RoleSeeder::class, IssueTypeSeeder::class]);

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
            ProjectSeeder::class,
            NotificationSeeder::class,
        ]);
    }
}
