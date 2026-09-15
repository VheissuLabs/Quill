<?php

namespace Database\Seeders;

use App\Models\Sprint;
use App\Models\Team;
use Illuminate\Database\Seeder;

/**
 * Every team has a running sprint. The first team in each organization also has a finished one
 * behind it and a planned one queued up, so the sprint list shows all three states.
 */
class SprintSeeder extends Seeder
{
    public function run(): void
    {
        $thisWeek = now()->startOfWeek();

        Team::all()->groupBy('organization_id')->each(function ($teams) use ($thisWeek) {
            $teams->each(function (Team $team, int $index) use ($thisWeek) {
                Sprint::factory()->for($team, 'owner')->active()->create([
                    'name' => 'Sprint '.($index === 0 ? 2 : 1),
                    'starts_at' => $thisWeek,
                    'ends_at' => $thisWeek->copy()->addWeeks(2)->subDay(),
                ]);

                if ($index === 0) {
                    Sprint::factory()->for($team, 'owner')->completed()->create([
                        'name' => 'Sprint 1',
                        'starts_at' => $thisWeek->copy()->subWeeks(2),
                        'ends_at' => $thisWeek->copy()->subDay(),
                        'completed_at' => $thisWeek->copy()->subDay()->setTime(17, 0),
                    ]);

                    Sprint::factory()->for($team, 'owner')->create([
                        'name' => 'Sprint 3',
                        'starts_at' => $thisWeek->copy()->addWeeks(2),
                        'ends_at' => $thisWeek->copy()->addWeeks(4)->subDay(),
                    ]);
                }
            });
        });
    }
}
