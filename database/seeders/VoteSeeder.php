<?php

namespace Database\Seeders;

use App\Enums\VoteDirection;
use App\Models\Feature;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Authors back their own request, then a random slice of users weigh in, mostly for and
 * occasionally against, so boards rank believably and a few scores go negative.
 */
class VoteSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();

        Feature::with('author')->get()->each(function (Feature $feature) use ($users) {
            if ($feature->author) {
                $feature->castVote($feature->author, VoteDirection::Up);
            }

            $users->random(fake()->numberBetween(0, 8))->each(
                fn (User $voter) => $feature->castVote($voter, fake()->boolean(20) ? VoteDirection::Down : VoteDirection::Up),
            );
        });
    }
}
