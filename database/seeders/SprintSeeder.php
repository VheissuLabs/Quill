<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Sprint;
use App\Models\Team;
use Illuminate\Database\Seeder;

/**
 * Every board has a running sprint. The first board in each organization also has a finished one
 * behind it and a planned one queued up, so the sprint list shows all three states. An
 * organization without teams runs its sprints itself.
 */
class SprintSeeder extends Seeder
{
    public function run(): void
    {
        $thisWeek = now()->startOfWeek();

        Organization::with('teams')->get()->each(function (Organization $organization) use ($thisWeek) {
            $owners = $organization->teams->isEmpty() ? collect([$organization]) : $organization->teams;

            $owners->values()->each(function (Organization|Team $team, int $index) use ($thisWeek) {
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
