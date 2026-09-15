<?php

namespace Database\Seeders;

use App\Enums\IssueStatus;
use App\Models\BoardColumn;
use App\Models\Organization;
use App\Models\Team;
use Illuminate\Database\Seeder;

/**
 * Teams start with Todo, In progress and Done. The first team in each organization adds an extra
 * in-progress column before Done, so custom boards and the status mapping are exercised.
 */
class BoardColumnSeeder extends Seeder
{
    public function run(): void
    {
        Organization::all()->each(function (Organization $organization) {
            $team = $organization->teams()->orderBy('id')->firstOrFail();

            $this->insertBeforeDone($team, fake()->randomElement(['In review', 'QA', 'Blocked', 'Ready to ship']));
        });
    }

    protected function insertBeforeDone(Team $team, string $name): void
    {
        $done = $team->columns()->where('status', IssueStatus::Done)->orderBy('position')->first();

        BoardColumn::factory()->for($team)->create([
            'name' => $name,
            'position' => $done ? $done->position : $team->columns()->count(),
        ]);

        $team->columns()->orderBy('position')->orderByDesc('id')->get()
            ->each(fn (BoardColumn $column, int $position) => $column->update(['position' => $position]));
    }
}
